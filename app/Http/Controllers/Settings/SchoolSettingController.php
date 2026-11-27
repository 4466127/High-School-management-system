<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SchoolType;
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
        'header_line', 'directorate', 'school_code', 'email', 'official_logos',
        'letter_outgoing_prefix', 'letter_incoming_prefix', 'letter_signature_1', 'letter_signature_2', 'letter_show_stamp',
        'fees_enabled', 'school_ledger_enabled',
        'pass_percent', 'conditional_max_failed_subjects', 'deprivation_absence_days', 'absence_alert_days', 'weekly_holidays',
        'grade_a_min', 'grade_b_min', 'grade_c_min',
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
        // تبدیل به «خصوصی» ← مدیریت فیس روشن (بعداً می‌شود خاموشش کرد؛ فقط هنگام تغییر نوع)
        $feesAutoEnabled = $data['school_type'] === SchoolType::Private->value
            && Setting::get('school_type') !== SchoolType::Private->value
            && ! Setting::enabled('fees_enabled');
        if ($feesAutoEnabled) {
            $data['fees_enabled'] = '1';
        }
        $data['school_ledger_enabled'] = $data['school_ledger_enabled'] ? '1' : '0';
        $data['letter_show_stamp'] = $data['letter_show_stamp'] ? '1' : '0';
        $data['official_logos'] = $data['official_logos'] ? '1' : '0';

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

        return redirect()->route('settings.school.edit')->with('success', $feesAutoEnabled ? __('school.fees_auto_enabled') : __('school.saved'));
    }

    /** لوگو برای همه (صفحه‌ی ورود هم آن را نشان می‌دهد) */
    public function logo(): StreamedResponse
    {
        $path = Setting::get('logo_path');
        abort_unless(PrivateFiles::exists($path), 404);

        return PrivateFiles::inline($path);
    }
}
