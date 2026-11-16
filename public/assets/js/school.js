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
