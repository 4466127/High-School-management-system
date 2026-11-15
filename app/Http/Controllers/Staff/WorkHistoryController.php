<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\WorkHistoryRequest;
use App\Models\Staff;
use App\Models\StaffWorkHistory;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;

class WorkHistoryController extends Controller
{
    public function __construct(private readonly StaffService $staffService) {}

    public function store(WorkHistoryRequest $request, Staff $staff): RedirectResponse
    {
        abort_if($staff->trashed(), 404);
        $this->staffService->addWorkHistory($staff, $request->validated());

        return redirect()->to(route('staff.show', $staff).'#history')->with('success', __('staff.history_added'));
    }

    public function destroy(Staff $staff, StaffWorkHistory $history): RedirectResponse
    {
        abort_unless($history->staff_id === $staff->id && ! $staff->trashed(), 404);
        $this->staffService->removeWorkHistory($staff, $history);

        return redirect()->to(route('staff.show', $staff).'#history')->with('success', __('staff.history_removed'));
    }
}
