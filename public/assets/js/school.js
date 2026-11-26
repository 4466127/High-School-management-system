'use strict';
/* =====================================================================
   افزوده‌های سیستم مدیریت مکتب (app.js از پروژه‌ی قبلی بدون تغییر است).
   - زبانه (tab) از روی آدرس: …/students/5#guardians ← زبانه‌ی سرپرستان باز می‌شود
   - پنجره‌ی فرمی که خطای اعتبارسنجی داشت دوباره باز می‌شود (.modal[data-open-on-load])
   ===================================================================== */
(function () {
    /* خانه‌ی فایل فارسی (x-form.input type=file): نام فایل انتخاب‌شده */
    document.addEventListener('change', function (ev) {
        var input = ev.target;
        if (!input.classList || !input.classList.contains('file-pick-input')) { return; }
        var label = input.closest('.file-pick');
        var name = label && label.querySelector('.file-pick-name');
        if (!name) { return; }
        name.textContent = input.files && input.files.length ? input.files[0].name : name.getAttribute('data-empty');
        label.classList.toggle('has-file', !!(input.files && input.files.length));
    });

    /* اعلان‌ها (toast): بعد از data-toast-ms بسته می‌شوند؛ نگه داشتن موس روی اعلان زمان را متوقف نمی‌کند (ساده) */
    function closeToast(toast) {
        if (!toast || toast.classList.contains('is-leaving')) { return; }
        toast.classList.add('is-leaving');
        setTimeout(function () {
            var stack = toast.parentNode;
            toast.remove();
            if (stack && !stack.children.length) { stack.remove(); }
        }, 300);
    }
    document.querySelectorAll('.app-toast[data-toast-ms]').forEach(function (toast) {
        var ms = parseInt(toast.getAttribute('data-toast-ms'), 10) || 10000;
        var bar = toast.querySelector('.app-toast-bar');
        if (bar) { bar.style.animationDuration = ms + 'ms'; }
        toast.classList.add('is-timing');
        setTimeout(function () { closeToast(toast); }, ms);
    });
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('[data-toast-close]');
        if (btn) { closeToast(btn.closest('.app-toast')); }
    });

    /* «همه» برای چک‌باکس‌های هم‌نام: <button data-check-all="section_ids[]"> */
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('[data-check-all]');
        if (!btn) { return; }
        var boxes = Array.prototype.slice.call(document.querySelectorAll('input[type=checkbox][name="' + btn.getAttribute('data-check-all') + '"]:not(:disabled)'));
        var allChecked = boxes.every(function (b) { return b.checked; });
        boxes.forEach(function (b) { b.checked = !allChecked; });
    });

    /* فهرست وابسته: <select data-filter-target="#x"> فقط گزینه‌های #x با data-type برابر مقدار خودش را نشان می‌دهد */
    document.querySelectorAll('select[data-filter-target]').forEach(function (source) {
        var target = document.querySelector(source.getAttribute('data-filter-target'));
        if (!target) { return; }
        function apply() {
            var firstVisible = null;
            Array.prototype.forEach.call(target.options, function (opt) {
                var show = !opt.hasAttribute('data-type') || opt.getAttribute('data-type') === source.value;
                opt.hidden = !show;
                opt.disabled = !show;
                if (show && firstVisible === null) { firstVisible = opt; }
            });
            if (target.selectedOptions.length === 0 || target.selectedOptions[0].disabled) {
                if (firstVisible) { firstVisible.selected = true; }
            }
            target.dispatchEvent(new Event('change', { bubbles: true }));
        }
        source.addEventListener('change', apply);
        apply();
    });

    /* مبلغ پیش‌فرض: <select data-amount-target="#amount"> با <option data-amount="1500"> */
    document.querySelectorAll('select[data-amount-target]').forEach(function (select) {
        var target = document.querySelector(select.getAttribute('data-amount-target'));
        select.addEventListener('change', function () {
            var opt = select.selectedOptions[0];
            if (target && opt && opt.getAttribute('data-amount') !== null) { target.value = opt.getAttribute('data-amount'); }
        });
    });

    /* منوی کنار: گروه باز (is-open) برای موبایل تمام عرض می‌شود */
    document.querySelectorAll('.nav-side .nav-sub.collapse').forEach(function (sub) {
        sub.addEventListener('show.bs.collapse', function () { sub.parentNode.classList.add('is-open'); });
        sub.addEventListener('hide.bs.collapse', function () { sub.parentNode.classList.remove('is-open'); });
    });

    /* ورق حاضری: «همه حاضر» و شمار زنده‌ی هر وضعیت (form[data-att-form]) */
    document.querySelectorAll('form[data-att-form]').forEach(function (form) {
        function refreshCounts() {
            var counts = {};
            form.querySelectorAll('input[type=radio][name^="status["]:checked').forEach(function (r) {
                counts[r.value] = (counts[r.value] || 0) + 1;
            });
            form.querySelectorAll('[data-att-count]').forEach(function (el) {
                var n = String(counts[el.getAttribute('data-att-count')] || 0);
                el.textContent = n.replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(+d); });
            });
        }
        form.addEventListener('change', refreshCounts);
        form.querySelectorAll('[data-att-all]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var value = btn.getAttribute('data-att-all');
                form.querySelectorAll('input[type=radio][name^="status["][value="' + value + '"]').forEach(function (r) { r.checked = true; });
                refreshCounts();
            });
        });
        refreshCounts();
    });

    // مشخصات مکتب: انتخاب «خصوصی» کلید مدیریت فیس را روشن می‌کند (سرور هم همین را هنگام ذخیره انجام می‌دهد)
    var schoolType = document.getElementById('school_type');
    var feesSwitch = document.getElementById('fees_enabled');
    if (schoolType && feesSwitch) {
        schoolType.addEventListener('change', function () {
            if (schoolType.value === 'private') { feesSwitch.checked = true; }
        });
    }

    if (!window.bootstrap) { return; }

    function showTabFromHash() {
        if (!location.hash || !/^#[\w-]+$/.test(location.hash)) { return; }
        var trigger = document.querySelector('[data-bs-toggle="tab"][data-bs-target="' + location.hash + '-pane"]');
        if (trigger) { window.bootstrap.Tab.getOrCreateInstance(trigger).show(); }
    }

    document.addEventListener('shown.bs.tab', function (ev) {
        var target = ev.target.getAttribute('data-bs-target');
        if (target && /-pane$/.test(target)) {
            history.replaceState(null, '', target.replace(/-pane$/, ''));
        }
    });

    showTabFromHash();

    document.querySelectorAll('.modal[data-open-on-load]').forEach(function (modal) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    });
})();

/* ---------- تقسیم اوقات (مرحله‌ی ۱۵): پنجره‌ی یک خانه ----------
   هر خانه‌ی جدول دکمه‌ای با data-tt-cell است؛ کلیک ← روز، ساعت و مقدارهای فعلی در پنجره‌ی #ttCell.
   با انتخاب مضمون، اگر معلم خالی است، معلم همان مضمون در این بخش (data-tt-defaults) گذاشته می‌شود. */
(function () {
    'use strict';
    var modal = document.getElementById('ttCell');
    if (!modal) { return; }
    var field = function (name) { return modal.querySelector('[data-tt-field="' + name + '"]'); };
    var label = modal.querySelector('[data-tt-label]');
    var subject = field('subject');
    var staff = field('staff');
    var defaults = {};
    try { defaults = JSON.parse(subject.getAttribute('data-tt-defaults') || '{}') || {}; } catch (e) { defaults = {}; }

    function labelFor(day, period) {
        var btn = document.querySelector('[data-tt-cell][data-day="' + day + '"][data-period="' + period + '"]');
        return btn ? btn.getAttribute('data-label') : '';
    }

    document.querySelectorAll('[data-tt-cell]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            field('day').value = btn.getAttribute('data-day');
            field('period').value = btn.getAttribute('data-period');
            subject.value = btn.getAttribute('data-subject') || '';
            staff.value = btn.getAttribute('data-staff') || '';
            field('room').value = btn.getAttribute('data-room') || '';
            field('note').value = btn.getAttribute('data-note') || '';
            modal.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            var clear = modal.querySelector('#tt-clear');
            if (clear) { clear.checked = false; }
            label.textContent = btn.getAttribute('data-label');
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    });

    subject.addEventListener('change', function () {
        if (!staff.value && defaults[subject.value]) { staff.value = String(defaults[subject.value]); }
    });

    // پس از خطا پنجره دوباره باز می‌شود؛ عنوان خانه را دوباره بگذار
    if (field('day').value) { label.textContent = labelFor(field('day').value, field('period').value); }
})();
