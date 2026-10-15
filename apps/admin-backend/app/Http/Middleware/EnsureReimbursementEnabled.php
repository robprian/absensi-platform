<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee-facing reimbursement endpoints are disabled (403) when the
 * admin turns the feature off. History stays readable by admins.
 */
class EnsureReimbursementEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!SystemSetting::isReimbursementEnabled()) {
            return response()->json([
                'message' => 'Fitur reimburse sedang dinonaktifkan oleh admin.',
                'code' => 'reimbursement_disabled',
            ], 403);
        }
        return $next($request);
    }
}
