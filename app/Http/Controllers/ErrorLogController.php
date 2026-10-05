<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
{
    // Sortable fields the client may send as `orderBy`, mapped to columns.
    private const SORTABLE = [
        'created_at' => 'error_logs.created_at',
        'id' => 'error_logs.id',
        'message' => 'error_logs.message',
        'method' => 'error_logs.method',
        'status_code' => 'error_logs.status_code',
        'url' => 'error_logs.url',
        'user_name' => 'users.name',
    ];

    public function index(Request $request)
    {
        $orderBy = self::SORTABLE[$request->input('orderBy')] ?? 'error_logs.created_at';
        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $quickFilter = $request->input('quickFilter');

        return ErrorLog::leftJoin('users', 'error_logs.user_id', '=', 'users.id')
            ->select(
                'error_logs.id',
                'error_logs.created_at',
                'error_logs.message',
                'error_logs.method',
                'error_logs.status_code',
                'error_logs.url',
                'users.name as user_name'
            )
            ->when($quickFilter, function ($query, $quickFilter) {
                $query->where(function ($q) use ($quickFilter) {
                    $q->where('error_logs.url', 'like', "%$quickFilter%")
                        ->orWhere('error_logs.message', 'like', "%$quickFilter%")
                        ->orWhere('error_logs.status_code', 'like', "%$quickFilter%")
                        ->orWhere('error_logs.method', 'like', "%$quickFilter%")
                        ->orWhere('error_logs.exception_class', 'like', "%$quickFilter%")
                        ->orWhere('users.name', 'like', "%$quickFilter%");
                });
            })
            ->orderBy($orderBy, $order)
            ->orderBy('error_logs.id', $order)
            ->paginate(10);
    }

    public function show(ErrorLog $errorLog)
    {
        return $errorLog->load('user:id,name');
    }
}
