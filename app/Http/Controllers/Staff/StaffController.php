<?php

namespace App\Http\Controllers\Staff;

use App\Enums\EmploymentStatus;
use App\Enums\StaffType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteWithReasonRequest;
use App\Http\Requests\Staff\StaffRequest;
use App\Http\Requests\Staff\StaffStatusRequest;
use App\Models\Staff;
use App\Services\StaffService;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** معلمان و کارکنان. همه‌ی مسیرها با can:staff.manage بسته شده‌اند (routes/web.php). */
class StaffController extends Controller
{
    public function __construct(private readonly StaffService $staffService) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'active');

        $query = Staff::query()->with('user:id,staff_id,username')->search($request->query('q'));

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif (EmploymentStatus::tryFrom($status)) {
            $query->where('employment_status', $status);
        }

        if ($type = StaffType::tryFrom((string) $request->query('type'))) {
            $query->where('staff_type', $type);
        }

        return view('staff.index', [
            'staff' => $query->orderBy('first_name')->orderBy('last_name')->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('staff.create', ['member' => new Staff(['staff_type' => StaffType::Teacher])]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $member = $this->staffService->create($request->staffData(), $request->file('photo'));

        return redirect()->route('staff.show', $member)->with('success', __('staff.created', ['name' => $member->full_name]));
    }

    public function show(Staff $staff): View
    {
        $staff->load(['workHistory', 'user.roles']);

        return view('staff.show', ['member' => $staff]);
    }

    public function edit(Staff $staff): View
    {
        abort_if($staff->trashed(), 404);

        return view('staff.edit', ['member' => $staff]);
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        abort_if($staff->trashed(), 404);
        $this->staffService->update($staff, $request->staffData(), $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('staff.show', $staff)->with('success', __('staff.updated', ['name' => $staff->full_name]));
    }

    public function changeStatus(StaffStatusRequest $request, Staff $staff): RedirectResponse
    {
        abort_if($staff->trashed(), 404);
        $data = $request->validated();
        $this->staffService->changeStatus($staff, EmploymentStatus::from($data['employment_status']), $data['reason'] ?? null, $data['date'] ?? null);

        return back()->with('success', __('staff.status_changed', ['name' => $staff->full_name, 'status' => $staff->employment_status->label()]));
    }

    public function destroy(DeleteWithReasonRequest $request, Staff $staff): RedirectResponse
    {
        abort_if($staff->trashed(), 404);
        $this->staffService->delete($staff, $request->validated('reason'));

        return redirect()->route('staff.index')->with('success', __('staff.deleted', ['name' => $staff->full_name]));
    }

    public function restore(Staff $staff): RedirectResponse
    {
        abort_unless($staff->trashed(), 404);
        $this->staffService->restore($staff);

        return redirect()->route('staff.show', $staff)->with('success', __('staff.restored', ['name' => $staff->full_name]));
    }

    public function photo(Staff $staff): StreamedResponse
    {
        abort_unless(PrivateFiles::exists($staff->photo_path), 404);

        return PrivateFiles::inline($staff->photo_path);
    }
}
