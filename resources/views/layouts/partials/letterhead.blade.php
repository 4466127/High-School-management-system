{{-- سربرگ مکتب برای همه‌ی چاپ‌ها (گزارش‌ها، کارنامه، مکاتیب، کارت). همه از تنظیمات؛ هیچ متن رسمی ثابت نیست.
     پارامترها: $withAddress (آدرس، تلفن، ایمیل)، $logoSize، $meta (برچسب ← مقدار در سمت چپ؛ پیش‌فرض تاریخ چاپ) --}}
@php
    $lhSetting = fn (string $key) => (string) \App\Models\Setting::get($key);
    $lhLines = array_filter([$lhSetting('header_line'), $lhSetting('directorate')]);
    $lhPlace = collect([__('school.type_line', ['type' => \App\Enums\SchoolType::current()->label()]), $lhSetting('province'), $lhSetting('district')])
        ->merge(($withAddress ?? false) ? [$lhSetting('address')] : [])->filter()->join('، ');
    $lhContact = ($withAddress ?? false) ? collect([$lhSetting('phone') !== '' ? \App\Support\Digits::toPersian($lhSetting('phone')) : '', $lhSetting('email')])->filter()->join(' · ') : '';
@endphp
<header class="print-head">
    <div class="d-flex align-items-center gap-2">
        @if ($lhOfficial = \App\Models\Setting::enabled('official_logos'))<img src="{{ asset('assets/images/moe/emblem.png') }}" alt="" class="print-emblem">@endif
        @if ($lhSetting('logo_path') !== '')<img src="{{ route('school.logo') }}" alt="" width="{{ $logoSize ?? 56 }}" height="{{ $logoSize ?? 56 }}" class="object-fit-contain">@endif
        <div>
            @foreach ($lhLines as $line)
                <div class="small fw-medium">{{ $line }}</div>
            @endforeach
            <div class="print-school">{{ $lhSetting('school_name') }}</div>
            <div class="small">{{ $lhPlace }}</div>
            @if ($lhContact !== '')<div class="small">{{ $lhContact }}</div>@endif
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="text-start small">
            @foreach ($meta ?? [__('app.printed_on') => \App\Support\JalaliDate::format(now())] as $lhLabel => $lhValue)
                <div>{{ $lhLabel }}: <span class="fw-medium">{{ $lhValue }}</span></div>
            @endforeach
        </div>
        @if ($lhOfficial)<img src="{{ asset('assets/images/moe/moe-logo.jpg') }}" alt="" class="print-moe-logo">@endif
    </div>
</header>
