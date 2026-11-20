<?php

namespace App\Listings;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Favourites (20 Nov 2026): homes a signed-in person saved with the heart. Only who and which
 * home are stored, in the favourites table. Someone who is not signed in is sent to sign in
 * (or create a free visitor account) and the home is saved as they arrive (savePending()).
 */
final class Favourites
{
    /** Session key holding the home a signed-out person pressed the heart on. */
    public const PENDING = 'favourite_pending';

    /** @var array<int, array<int, true>> user id => listing ids, once per request */
    private static array $loaded = [];

    /** @return array<int, true> listing ids saved by this user, for quick look-ups on cards */
    public static function idsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return self::$loaded[$user->id] ??= DB::table('favourites')->where('user_id', $user->id)
            ->pluck('listing_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    public static function has(?User $user, Listing $listing): bool
    {
        return isset(self::idsFor($user)[$listing->id]);
    }

    public static function add(User $user, Listing $listing): void
    {
        DB::table('favourites')->insertOrIgnore(['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => now()]);
        unset(self::$loaded[$user->id]);
    }

    public static function remove(User $user, Listing $listing): void
    {
        DB::table('favourites')->where(['user_id' => $user->id, 'listing_id' => $listing->id])->delete();
        unset(self::$loaded[$user->id]);
    }

    /** Saves the home a person pressed the heart on before signing in or creating an account. */
    public static function savePending(User $user): void
    {
        $ref = session()->pull(self::PENDING);
        if (! is_string($ref)) {
            return;
        }
        $listing = Listing::live()->where('public_ref', $ref)->first();
        if ($listing) {
            self::add($user, $listing);
            session()->flash('status', __('Saved to your favourites.'));
        }
    }

    /** Forgets what was loaded (tests, and after a user changes). */
    public static function flush(): void
    {
        self::$loaded = [];
    }
}
