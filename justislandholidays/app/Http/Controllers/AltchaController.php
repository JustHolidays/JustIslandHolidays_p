<?php

namespace App\Http\Controllers;

use App\Support\Spam\AltchaGuard;
use Illuminate\Http\JsonResponse;

class AltchaController extends Controller
{
    public function __invoke(AltchaGuard $guard): JsonResponse
    {
        return response()->json($guard->challenge())
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
