<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $q = AuditLog::with('actor')
            ->when($request->action, fn ($qq, $a) => $qq->where('action', 'like', "%$a%"))
            ->when($request->date_from, fn ($qq, $d) => $qq->where('created_at', '>=', $d))
            ->when($request->date_to, fn ($qq, $d) => $qq->where('created_at', '<=', $d))
            ->orderByDesc('id');

        return response()->json($q->paginate(30));
    }
}
