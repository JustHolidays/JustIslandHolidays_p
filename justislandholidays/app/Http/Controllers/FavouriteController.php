<?php

namespace App\Http\Controllers;

use App\Listings\Favourites;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Favourites (20 Nov 2026). The heart on a property page or card saves the home for a signed-in
 * person, or takes a signed-out one to sign in (or create a free visitor account) first. Their
 * saved homes are on My favourites in their account.
 */
class FavouriteController extends Controller
{
    /** The heart, signed in: saves the home, or removes it when it is already saved. */
    public function toggle(Request $request, string $ref): RedirectResponse
    {
        $listing = $this->live($ref);
        $user = $request->user();

        if (Favourites::has($user, $listing)) {
            Favourites::remove($user, $listing);
            $message = __('Removed from your favourites.');
        } else {
            Favourites::add($user, $listing);
            $message = __('Saved to your favourites.');
        }

        return back(fallback: $listing->publicUrl())->with('status', $message);
    }

    /** The heart, signed out: remember the home, then sign in and come back with it saved. */
    public function signIn(Request $request, string $ref): RedirectResponse
    {
        $listing = $this->live($ref);

        if ($request->user()) {
            Favourites::add($request->user(), $listing);

            return redirect()->to($listing->publicUrl())->with('status', __('Saved to your favourites.'));
        }

        $request->session()->put(Favourites::PENDING, $listing->public_ref);
        $request->session()->put('url.intended', $listing->publicUrl());

        return redirect()->route('login')->with('favourite_prompt', true);
    }

    /** My favourites: the homes this person has saved, newest first. */
    public function index(Request $request): View
    {
        $ids = DB::table('favourites')->where('user_id', $request->user()->id)->orderByDesc('created_at')->pluck('listing_id');
        $listings = Listing::withTrashed()->whereIn('id', $ids)->with(['island', 'area', 'translations'])->get()
            ->sortBy(fn (Listing $l) => $ids->search($l->id))->values();

        return view('account.favourites', ['listings' => $listings]);
    }

    /** Removes a saved home from My favourites, including one that is no longer listed. */
    public function destroy(Request $request, int $listing): RedirectResponse
    {
        DB::table('favourites')->where(['user_id' => $request->user()->id, 'listing_id' => $listing])->delete();
        Favourites::flush();

        return back()->with('status', __('Removed from your favourites.'));
    }

    private function live(string $ref): Listing
    {
        $listing = Listing::live()->where('public_ref', $ref)->with(['island', 'area'])->first();
        abort_unless($listing && $listing->island->is_active, 404);

        return $listing;
    }
}
