<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ListingStatus;
use App\Models\AdminRecommendation;
use App\Models\AffiliateCommission;
use App\Models\AuditLog;
use App\Models\DeeplinkClick;
use App\Models\Listing;
use App\Models\ListingLicence;
use Illuminate\View\View;

class DashboardController extends AdminController
{
    public function __invoke(): View
    {
        $ctx = $this->ctx();
        $admin = $ctx->admin();
        $target = (int) config('site.review_target_hours');

        $clicks = fn ($from, $to) => $ctx->scope(DeeplinkClick::query())
            ->where('is_bot', false)->whereBetween('clicked_at', [$from, $to])->count();

        $brokenLinks = fn (bool $affiliates) => $ctx->scope(Listing::query())->where('status', ListingStatus::Live)
            ->whereNotNull('deeplink_url')->where('deeplink_status', 'broken')
            ->whereHas('owner', fn ($q) => $affiliates ? $q->where('role', 'affiliate') : $q->where('role', '!=', 'affiliate'))
            ->count();

        $data = [
            'pending' => $ctx->scope(Listing::query())->where('status', ListingStatus::PendingReview)->count(),
            'overdue' => $ctx->scope(Listing::query())->overdue($target)->count(),
            'licences' => $ctx->scopeVia(ListingLicence::query(), 'listing')->where('status', 'pending')->count(),
            // Owner and agent links; affiliates' broken links are counted separately for super admins.
            'broken' => $brokenLinks(false),
            'brokenAffiliate' => null,
            'clicks7' => $clicks(now()->subDays(7), now()),
            'clicksPrev7' => $clicks(now()->subDays(14), now()->subDays(7)),
            'target' => $target,
            'recommendations' => null,
            'commission' => collect(),
            'audit' => collect(),
        ];

        if ($admin->isSuperAdmin()) {
            $data['brokenAffiliate'] = $brokenLinks(true);
            $data['recommendations'] = $ctx->scope(AdminRecommendation::query())->where('status', 'open')->count();
            $data['commission'] = $ctx->scopeVia(AffiliateCommission::query(), 'listing')->where('status', 'pending')
                ->selectRaw('currency, SUM(commission_cents) as total')->groupBy('currency')->pluck('total', 'currency');
            $data['audit'] = AuditLog::with('actor')->latest('id')->limit(8)->get();
        }

        return view('admin.dashboard', $data);
    }
}
