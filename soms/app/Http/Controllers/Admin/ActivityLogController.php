<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;


class ActivityLogController extends Controller {

public function index(Request $request) {
    $query = ActivityLog::query()->with('user')->latest();
    
    if ($userId = $request->input('user_id')){
        $query->where('user_id', $userId);
    }
    if($action = $request->input('action')){
        $query->where('action', $action);
    }
    if ($from = $request->input('from')){
        $query->whereDate('created_at', '>=', $from);  
    }
    if ($to = $request->input('to')){
        $query->whereDate('created_at', '<=', $to);
    }

    return view('admin.activity-logs', [
        'logs' => $query->paginate(15)->withQueryString(),
        'logChainOk' => ActivityLog::verifyChainIntegrity(),
    ]);
}
}