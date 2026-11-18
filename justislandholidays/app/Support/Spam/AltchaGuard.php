<?php

namespace App\Support\Spam;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * ALTCHA proof-of-work, self-hosted: we issue signed challenges and verify the
 * visitor's solution. Each solution can be used once (replay protection).
 */
class AltchaGuard
{
    private Altcha $altcha;

    public function __construct()
    {
        $key = (string) config('site.altcha.hmac_key');

        $this->altcha = new Altcha(
            hmacSignatureSecret: $key,
            hmacKeySignatureSecret: hash('sha256', 'key-signature|'.$key),
        );
    }

    /** @return array<string, mixed> */
    public function challenge(): array
    {
        $min = (int) config('site.altcha.counter_min');
        $max = max($min, (int) config('site.altcha.counter_max'));

        return $this->altcha->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: (int) config('site.altcha.cost'),
            counter: random_int($min, $max),
            expiresAt: time() + 60 * (int) config('site.altcha.expires_minutes'),
        ))->toArray();
    }

    public function verify(mixed $payload): bool
    {
        if (! is_string($payload) || $payload === '' || strlen($payload) > 8192) {
            return false;
        }

        try {
            $result = $this->altcha->verifySolution(new VerifySolutionOptions(payload: $payload, algorithm: new Pbkdf2()));
        } catch (Throwable) {
            return false;
        }

        if (! $result->verified) {
            return false;
        }

        // A solved challenge works once.
        $ttl = 60 * ((int) config('site.altcha.expires_minutes') + 5);

        return Cache::add('altcha:used:'.hash('sha256', $payload), true, $ttl);
    }
}
