<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    // Sortable fields the client may send as `orderBy`, mapped to columns.
    private const SORTABLE = [
        'auditable_id' => 'audit_logs.auditable_id',
        'created_at' => 'audit_logs.created_at',
        'event' => 'audit_logs.event',
        'id' => 'audit_logs.id',
        'label' => 'audit_logs.label',
        'user_name' => 'users.name',
    ];

    /**
     * Audit logs of one type (product, category, user or transaction).
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        if (!in_array($type, AuditLog::TYPES, true)) {
            return response()->json([
                'error' => 'The selected type is invalid.',
                'statusCode' => 400,
            ], 400);
        }

        $orderBy = self::SORTABLE[$request->input('orderBy')] ?? 'audit_logs.created_at';
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $quickFilter = $request->input('quickFilter');

        return AuditLog::leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->select(
                'audit_logs.id',
                'audit_logs.auditable_id',
                'audit_logs.created_at',
                'audit_logs.event',
                'audit_logs.label',
                'users.name as user_name'
            )
            ->where('audit_logs.auditable_type', $type)
            ->when($quickFilter, function ($query, $quickFilter) {
                $query->where(function ($q) use ($quickFilter) {
                    $q->where('audit_logs.label', 'like', "%$quickFilter%")
                        ->orWhere('audit_logs.event', 'like', "%$quickFilter%")
                        ->orWhere('audit_logs.auditable_id', 'like', "%$quickFilter%")
                        ->orWhere('users.name', 'like', "%$quickFilter%");
                });
            })
            ->orderBy($orderBy, $order)
            ->orderBy('audit_logs.id', $order)
            ->paginate(10);
    }

    /**
     * Number of audit logs per type, e.g. {"product": 12, "category": 3, ...}.
     */
    public function summary()
    {
        $counts = AuditLog::selectRaw('auditable_type, count(*) as total')
            ->groupBy('auditable_type')
            ->pluck('total', 'auditable_type');

        return collect(AuditLog::TYPES)
            ->mapWithKeys(fn ($type) => [$type => (int) ($counts[$type] ?? 0)]);
    }

    public function show(AuditLog $auditLog)
    {
        return $auditLog->load('user:id,name');
    }
}
