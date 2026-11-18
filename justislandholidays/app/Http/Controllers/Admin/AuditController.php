<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Read-only audit log (super admins). Entries can never be edited or deleted from here. */
class AuditController extends AdminController
{
    public function index(Request $request): View
    {
        return view('admin.audit.index', [
            'entries' => $this->filtered($request)->with('actor')->latest('id')->paginate(50)->withQueryString(),
            'actors' => User::whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))->withTrashed()->orderBy('name')->get(['id', 'name']),
            'entities' => AuditLog::query()->distinct()->orderBy('entity')->pluck('entity'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->with('actor')->orderBy('id')->lazy(500);

        return CsvExport::download('audit-log-'.now()->format('Ymd-His').'.csv', ['Time', 'Admin', 'Action', 'Record type', 'Record id', 'Detail'], (function () use ($rows) {
            foreach ($rows as $e) {
                yield [$e->created_at, $e->actor?->name ?? 'System', $e->action, $e->entity, $e->entity_id, $e->detail];
            }
        })());
    }

    private function filtered(Request $request): Builder
    {
        $q = AuditLog::query();

        if ($request->query('actor') === 'system') {
            $q->whereNull('actor_id');
        } elseif ($id = $request->integer('actor')) {
            $q->where('actor_id', $id);
        }
        if (is_string($action = $request->query('action')) && $action !== '') {
            $q->where('action', 'like', $this->like($action));
        }
        if (is_string($entity = $request->query('entity')) && preg_match('/^[a-z_]{1,60}$/', $entity)) {
            $q->where('entity', $entity);
        }
        if (is_string($id = $request->query('record')) && $id !== '') {
            $q->where('entity_id', $id);
        }
        foreach (['from' => 'startOfDay', 'to' => 'endOfDay'] as $param => $edge) {
            if ($date = rescue(fn () => $request->query($param) ? Carbon::parse($request->query($param))->{$edge}() : null, null, false)) {
                $q->where('created_at', $param === 'from' ? '>=' : '<=', $date);
            }
        }

        return $q;
    }
}
