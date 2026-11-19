<?php

namespace App\Billing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the PayPal REST API (Subscriptions and webhook verification).
 * No card data ever reaches our servers: the payer approves on PayPal's own pages.
 */
class PayPalClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.paypal.client_id')) && filled(config('services.paypal.client_secret'));
    }

    public function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * @return array{id: string, status: string, approve_url: ?string}
     */
    public function createSubscription(string $planId, string $customId, string $returnUrl, string $cancelUrl): array
    {
        $body = $this->request()
            ->withHeaders(['PayPal-Request-Id' => 'jih-sub-'.$customId])   // safe to retry
            ->post('/v1/billing/subscriptions', [
                'plan_id' => $planId,
                'custom_id' => $customId,
                'application_context' => [
                    'brand_name' => config('services.paypal.brand_name'),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ])
            ->throw(fn (Response $r) => throw new PayPalException('PayPal could not start the subscription: '.$r->status()))
            ->json();

        return [
            'id' => $body['id'],
            'status' => $body['status'] ?? 'APPROVAL_PENDING',
            'approve_url' => collect($body['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null,
        ];
    }

    /**
     * A one-off payment (Featured Property periods). The payer approves it on PayPal, then
     * we capture it when they come back.
     *
     * @return array{id: string, approve_url: ?string}
     */
    public function createOrder(int $amountCents, string $currency, string $customId, string $description, string $returnUrl, string $cancelUrl): array
    {
        $body = $this->request()
            ->withHeaders(['PayPal-Request-Id' => 'jih-order-'.$customId])   // safe to retry
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'custom_id' => $customId,
                    'description' => mb_substr($description, 0, 127),
                    'amount' => ['currency_code' => $currency, 'value' => number_format($amountCents / 100, 2, '.', '')],
                ]],
                'application_context' => [
                    'brand_name' => config('services.paypal.brand_name'),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ])
            ->throw(fn (Response $r) => throw new PayPalException('PayPal could not start the payment: '.$r->status()))
            ->json();

        return [
            'id' => $body['id'],
            'approve_url' => collect($body['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null,
        ];
    }

    /** Takes the approved payment. Returns PayPal's status, 'COMPLETED' when the money is taken. */
    public function captureOrder(string $orderId): string
    {
        $response = $this->request()
            ->withHeaders(['PayPal-Request-Id' => 'jih-capture-'.$orderId])
            ->post("/v2/checkout/orders/{$orderId}/capture", (object) []);

        // Already captured (a refresh, or a second tab) is fine: ask for the order's status.
        if ($response->status() === 422 && str_contains((string) $response->body(), 'ORDER_ALREADY_CAPTURED')) {
            return (string) $this->request()->get("/v2/checkout/orders/{$orderId}")->json('status', 'COMPLETED');
        }
        if (! $response->successful()) {
            throw new PayPalException('PayPal could not take the payment: '.$response->status());
        }

        return (string) $response->json('status');
    }

    /** Switch an active subscription to another plan. Returns the approval link if PayPal needs one. */
    public function revisePlan(string $subscriptionId, string $planId, string $returnUrl, string $cancelUrl): ?string
    {
        $body = $this->request()
            ->post("/v1/billing/subscriptions/{$subscriptionId}/revise", [
                'plan_id' => $planId,
                'application_context' => [
                    'brand_name' => config('services.paypal.brand_name'),
                    'shipping_preference' => 'NO_SHIPPING',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ])
            ->throw(fn (Response $r) => throw new PayPalException('PayPal could not change the plan: '.$r->status()))
            ->json();

        return collect($body['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;
    }

    /** @return array<string, mixed> */
    public function getSubscription(string $subscriptionId): array
    {
        return $this->request()
            ->get('/v1/billing/subscriptions/'.rawurlencode($subscriptionId))
            ->throw(fn (Response $r) => throw new PayPalException('PayPal subscription lookup failed: '.$r->status()))
            ->json();
    }

    public function cancelSubscription(string $subscriptionId, string $reason): void
    {
        $this->request()
            ->post('/v1/billing/subscriptions/'.rawurlencode($subscriptionId).'/cancel', ['reason' => mb_substr($reason, 0, 128)])
            ->throw(fn (Response $r) => throw new PayPalException('PayPal could not cancel the subscription: '.$r->status()));
    }

    /**
     * Asks PayPal whether a webhook really came from PayPal.
     *
     * @param  array<string, mixed>  $event  the decoded webhook body
     */
    public function verifyWebhook(array $headers, array $event): bool
    {
        $webhookId = config('services.paypal.webhook_id');
        $header = fn (string $name) => $headers[strtolower($name)][0] ?? null;

        if (! $webhookId || ! $header('paypal-transmission-sig')) {
            return false;
        }

        $response = $this->request()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $header('paypal-auth-algo'),
            'cert_url' => $header('paypal-cert-url'),
            'transmission_id' => $header('paypal-transmission-id'),
            'transmission_sig' => $header('paypal-transmission-sig'),
            'transmission_time' => $header('paypal-transmission-time'),
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new PayPalException('PayPal is not set up.');
        }

        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    private function accessToken(): string
    {
        $cacheKey = 'paypal.token.'.config('services.paypal.mode').'.'.md5((string) config('services.paypal.client_id'));

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $response = Http::baseUrl($this->baseUrl())
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->asForm()
            ->timeout(20)
            ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw(fn (Response $r) => throw new PayPalException('PayPal sign-in failed: '.$r->status()));

        $token = $response->json('access_token');
        Cache::put($cacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }
}
