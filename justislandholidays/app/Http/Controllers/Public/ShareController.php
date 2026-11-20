<?php

namespace App\Http\Controllers\Public;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;

/**
 * /share/{network}/{ref} (20 Nov 2026): the share buttons point here, on our own site, and this
 * passes the visitor on to the network's own share page. Ad and tracker blockers hide links that
 * point straight at those share pages, which left visitors seeing only the word "Share".
 * Stateless: no session or cookies, and nothing is recorded.
 */
class ShareController extends Controller
{
    public const NETWORKS = ['facebook', 'whatsapp', 'x', 'pinterest'];

    public function __invoke(string $network, string $ref): RedirectResponse
    {
        $listing = Listing::where('public_ref', $ref)->with(['island', 'area', 'translations'])->first();
        abort_unless($listing && $listing->status === ListingStatus::Live && $listing->island->is_active, 404);

        $lang = in_array(request()->query('l'), config('site.enabled_languages'), true) ? request()->query('l') : 'en';
        $url = rawurlencode($listing->publicUrl($lang));
        $text = rawurlencode($listing->title($lang).' – '.$listing->area->translate('name', $lang).', '.$listing->island->translate('name', $lang));
        $image = $listing->imageUrl(1600);

        $target = match ($network) {
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.$url,
            'whatsapp' => 'https://wa.me/?text='.$text.'%20'.$url,
            'x' => 'https://twitter.com/intent/tweet?url='.$url.'&text='.$text,
            'pinterest' => 'https://pinterest.com/pin/create/button/?url='.$url.'&description='.$text.($image ? '&media='.rawurlencode($image) : ''),
        };

        return redirect()->away($target)->withHeaders(['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
