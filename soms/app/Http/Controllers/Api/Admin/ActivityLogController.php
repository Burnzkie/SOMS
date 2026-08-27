<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Mobile counterpart to Http\Controllers\Admin\ActivityLogController.
 * See 09-Admin-Dashboard.md, Activity Logs (searchable, paginated, with
 * chain integrity indicator) and 10-Mobile-Deployment.md (Admin screen:
 * "Activity logs (with chain integrity indicator)").
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()->with('user')->latest();

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return response()->json(['success' => true, 'data' => [
            'logs'       => $query->paginate(15)->withQueryString(),
            'logChainOk' => ActivityLog::verifyChainIntegrity(),
        ]]);
    }
}
