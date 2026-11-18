<?php

namespace Tests\Feature\Public;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Support\Spam\AltchaGuard;
use Tests\TestCase;

/** A real round trip: challenge from our endpoint, solved as the browser would, verified once. */
class AltchaGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Make the proof of work tiny so the test is fast.
        config(['site.altcha.cost' => 1, 'site.altcha.counter_min' => 1, 'site.altcha.counter_max' => 20]);
    }

    private function solvedPayload(): string
    {
        $json = $this->getJson('/altcha/challenge')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $challenge = Challenge::fromArray($json);

        $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(challenge: $challenge, algorithm: new Pbkdf2()));
        $this->assertNotNull($solution);

        return (new Payload($challenge, $solution))->toBase64();
    }

    public function test_a_solved_challenge_verifies_once(): void
    {
        $payload = $this->solvedPayload();
        $guard = app(AltchaGuard::class);

        $this->assertTrue($guard->verify($payload));
        $this->assertFalse($guard->verify($payload), 'A solution cannot be reused.');
    }

    public function test_garbage_and_tampered_payloads_fail(): void
    {
        $guard = app(AltchaGuard::class);

        $this->assertFalse($guard->verify(null));
        $this->assertFalse($guard->verify(''));
        $this->assertFalse($guard->verify('not-base64!'));

        $data = json_decode(base64_decode($this->solvedPayload()), true);
        $this->assertArrayHasKey('parameters', $data['challenge']);
        $data['challenge']['parameters']['cost'] = 2;
        $this->assertFalse($guard->verify(base64_encode(json_encode($data))), 'Changed parameters break the signature.');
    }

    public function test_a_challenge_signed_with_another_key_fails(): void
    {
        $payload = $this->solvedPayload();
        config(['site.altcha.hmac_key' => 'a-different-key']);

        $this->assertFalse((new AltchaGuard())->verify($payload));
    }
}
