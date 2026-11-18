<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AccountStatus;
use App\Listings\ListingWorkflow;
use App\Listings\ListingWorkflowException;
use App\Models\AdminRecommendation;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** A super admin accepts or declines an island admin's recommendation. */
class RecommendationController extends AdminController
{
    public function decide(Request $request, AdminRecommendation $recommendation, ListingWorkflow $workflow, AccountStatus $accounts): RedirectResponse
    {
        abort_unless(Gate::allows('decide', $recommendation), 403);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'decline'])],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);
        $note = trim((string) ($data['decision_note'] ?? ''));
        $admin = $request->user();

        try {
            DB::transaction(function () use ($recommendation, $data, $note, $admin, $workflow, $accounts) {
                if ($data['decision'] === 'accept') {
                    $reason = mb_substr(trim($recommendation->reason.($note !== '' ? ' '.$note : '')), 0, 500);

                    $recommendation->action === 'reject_listing'
                        ? $workflow->rejectOnRecommendation($recommendation->listing, $admin, $reason)
                        : $accounts->deactivate($recommendation->user, $admin, $reason);
                }

                $recommendation->forceFill([
                    'status' => $data['decision'] === 'accept' ? 'accepted' : 'declined',
                    'decided_by' => $admin->id,
                    'decision_note' => $note !== '' ? $note : null,
                    'decided_at' => now(),
                ])->save();

                Audit::record($admin, 'recommendation.'.$data['decision'], $recommendation, null, ['note' => $note]);
            });
        } catch (ListingWorkflowException $e) {
            return back()->withErrors([$e->getMessage(), ...$e->problems]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', $data['decision'] === 'accept' ? 'Done. The lister has been emailed the reason.' : 'Sent back. Nothing has changed for the lister.');
    }
}
