<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $from = JalaliDate::parse($request->query('from'));
        $to = JalaliDate::parse($request->query('to'));

        $logs = ActivityLog::query()
            ->with('user:id,name,username')
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->query('action').'%'))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('settings.activity-logs.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actionGroups' => array_keys(__('activity.groups')),
        ]);
    }
}
