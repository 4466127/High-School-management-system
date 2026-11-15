<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SchoolSettingsRequest;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** مشخصات مکتب، کلیدهای مالی و قاعده‌های نمره‌دهی (مجوز: settings.manage). */
class SchoolSettingController extends Controller
{
    public const KEYS = [
        'school_name', 'school_type', 'province', 'district', 'address', 'phone', 'principal_name',
        'fees_enabled', 'school_ledger_enabled',
        'pass_percent', 'conditional_max_failed_subjects', 'deprivation_absence_days', 'weekly_holidays',
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    public function edit(): View
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => Setting::get($key)])->all();
        $values['weekly_holidays'] = array_filter(explode(',', (string) $values['weekly_holidays']));

        return view('settings.school.edit', ['values' => $values, 'logo' => Setting::get('logo_path')]);
    }

    public function update(SchoolSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['weekly_holidays'] = implode(',', $data['weekly_holidays'] ?? []);
        $data['fees_enabled'] = $data['fees_enabled'] ? '1' : '0';
        $data['school_ledger_enabled'] = $data['school_ledger_enabled'] ? '1' : '0';

        $oldLogo = Setting::get('logo_path');
        $newLogo = $request->hasFile('logo') ? PrivateFiles::store($request->file('logo'), 'school') : null;

        DB::transaction(function () use ($data, $newLogo, $oldLogo, $request) {
            $old = [];
            $new = [];
            foreach (self::KEYS as $key) {
                $before = (string) Setting::get($key);
                $after = (string) ($data[$key] ?? '');
                if ($before !== $after) {
                    $old[$key] = $before;
                    $new[$key] = $after;
                    Setting::put($key, $after);
                }
            }

            if ($newLogo || ($request->boolean('remove_logo') && $oldLogo)) {
                Setting::put('logo_path', $newLogo ?? '');
                $old['logo'] = $oldLogo ? '✓' : '';
                $new['logo'] = $newLogo ? '✓' : '';
            }

            if ($new !== []) {
                $this->logger->log('settings.updated', null, __('school.title'), $old, $new);
            }
        });

        if ($oldLogo && ($newLogo || $request->boolean('remove_logo'))) {
            PrivateFiles::delete($oldLogo);
        }

        return redirect()->route('settings.school.edit')->with('success', __('school.saved'));
    }

    /** لوگو برای همه (صفحه‌ی ورود هم آن را نشان می‌دهد) */
    public function logo(): StreamedResponse
    {
        $path = Setting::get('logo_path');
        abort_unless(PrivateFiles::exists($path), 404);

        return PrivateFiles::inline($path);
    }
}
