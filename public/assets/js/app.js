'use strict';
/* =====================================================================
   جاوااسکریپت عمومی برنامه — بدون کتابخانه‌ی بیرونی (به جز Bootstrap که جدا بار می‌شود).
   متن‌ها از فایل ترجمه می‌آیند (عنصر #hk-lang که سرور در صفحه می‌گذارد).
   ===================================================================== */
(function () {
    function meta(name) {
        const el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    }

    const base = meta('hk-base');

    let lang = {};
    try {
        const el = document.getElementById('hk-lang');
        if (el) { lang = JSON.parse(el.textContent || '{}'); }
    } catch (e) {
        lang = {};
    }

    /** خواندن متن ترجمه */
    function t(key) {
        return Object.prototype.hasOwnProperty.call(lang, key) ? lang[key] : key;
    }

    /**
     * صدا زدن API داخلی. توکن امنیتی (CSRF) خودکار فرستاده می‌شود.
     * اگر ok=false بود، خطا با پیام سرور پرتاب می‌شود.
     */
    async function api(path, options) {
        const opts = Object.assign({ credentials: 'same-origin' }, options || {});
        opts.headers = Object.assign({ Accept: 'application/json', 'X-CSRF-Token': meta('hk-csrf') }, opts.headers || {});
        if (opts.body && typeof opts.body !== 'string') {
            opts.body = JSON.stringify(opts.body);
            opts.headers['Content-Type'] = 'application/json';
        }
        let response;
        try {
            response = await fetch(base + '/api/v1' + path, opts);
        } catch (e) {
            throw new Error(t('request_failed'));
        }
        let data;
        try {
            data = await response.json();
        } catch (e) {
            throw new Error(t('request_failed'));
        }
        if (!data || data.ok !== true) {
            const err = new Error((data && data.error && data.error.message) || t('request_failed'));
            err.code = (data && data.error && data.error.code) || '';
            err.details = (data && data.error && data.error.details) || null;
            throw err;
        }
        return data.data;
    }

    window.HK = { base: base, t: t, api: api };

    /* ---------- فورم‌هایی که پیش از فرستادن تأیید می‌خواهند (data-confirm="...") ---------- */
    document.addEventListener('submit', function (ev) {
        const form = ev.target;
        const message = form.getAttribute && form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            ev.preventDefault();
        }
    });

    /* ---------- دکمه‌ای که پیش از فرستادن تأیید می‌خواهد (button data-confirm-click="...")؛ دکمه‌های دیگر همان فورم نه ---------- */
    document.addEventListener('click', function (ev) {
        const button = ev.target.closest('[data-confirm-click]');
        if (button && !window.confirm(button.getAttribute('data-confirm-click'))) {
            ev.preventDefault();
        }
    });

    /* ---------- فورم‌هایی که کارشان طول می‌کشد (data-busy="متن"): دکمه خاموش و «در حال ...» ---------- */
    document.addEventListener('submit', function (ev) {
        const form = ev.target;
        const busy = form.getAttribute && form.getAttribute('data-busy');
        if (!busy || ev.defaultPrevented) { return; }
        form.querySelectorAll('button[type="submit"]').forEach(function (b) {
            b.disabled = true;
            b.textContent = busy;
        });
    });

    /* ---------- دکمه‌ی «انتخاب همه» در گروه checkboxها (صفحه‌ی نقش‌ها) ---------- */
    document.addEventListener('click', function (ev) {
        const button = ev.target.closest('[data-check-all]');
        if (!button) { return; }
        const group = button.closest('[data-check-group]');
        if (!group) { return; }
        const boxes = Array.prototype.slice.call(group.querySelectorAll('input[type="checkbox"]:not(:disabled)'));
        const allChecked = boxes.every(function (b) { return b.checked; });
        boxes.forEach(function (b) { b.checked = !allChecked; });
    });

    /* ---------- فورم اصلاح موجودی: ستون «قیمت» فقط برای موجودی اول دوره ---------- */
    const reasonSelect = document.getElementById('reason');
    if (reasonSelect && document.querySelector('.opening-only')) {
        const toggleOpening = function () {
            document.querySelectorAll('.opening-only').forEach(function (el) {
                el.classList.toggle('d-none', reasonSelect.value !== 'opening');
            });
        };
        reasonSelect.addEventListener('change', toggleOpening);
        toggleOpening();
    }

    /* ---------- خلاصه‌ی خطاهای فورم: کلیک روی هر خطا ← رفتن به همان خانه (data-field="نام خانه") ---------- */
    document.addEventListener('click', function (ev) {
        const link = ev.target.closest('[data-field]');
        if (!link) { return; }
        const name = link.getAttribute('data-field');
        const field = Array.prototype.find.call(document.querySelectorAll('form [name]'), function (el) {
            return el.getAttribute('name') === name && el.type !== 'hidden';
        });
        if (!field) { return; }
        ev.preventDefault();
        field.scrollIntoView({ block: 'center' });
        if (typeof field.focus === 'function') { field.focus({ preventScroll: true }); }
    });

    /* ---------- جستجو در سطرهای یک جدول (input data-filter-rows="#id"؛ هر سطر data-search) ---------- */
    const normalize = function (s) {
        return String(s || '').toLowerCase()
            .replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/[ۀة]/g, 'ه').replace(/[أإآ]/g, 'ا')
            .replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
            .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
            .replace(/[‌ً-ْ]/g, '').replace(/\s+/g, ' ').trim();
    };
    document.querySelectorAll('[data-filter-rows]').forEach(function (input) {
        const table = document.querySelector(input.getAttribute('data-filter-rows'));
        if (!table) { return; }
        input.addEventListener('input', function () {
            const q = normalize(input.value);
            table.querySelectorAll('tbody tr[data-search]').forEach(function (tr) {
                // سطری که مبلغ دارد همیشه دیده شود
                const filled = Array.prototype.some.call(tr.querySelectorAll('input[name$="[amount]"]'), function (i) { return i.value.trim() !== ''; });
                tr.classList.toggle('d-none', q !== '' && !filled && normalize(tr.getAttribute('data-search')).indexOf(q) === -1);
            });
        });
    });

    /* ---------- کرایه‌ی «سیرانه»: کرایه = مقدار × کرایه‌ی هر سیر/کیلو (data-calc="qty|unit|rate|amount") ---------- */
    const toNumber = function (s) {
        const en = String(s || '')
            .replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
            .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
            .replace(/[,٬،\s]/g, '').replace(/٫/g, '.');
        return en === '' || isNaN(Number(en)) ? null : Number(en);
    };
    document.querySelectorAll('form').forEach(function (calcForm) {
        const qty = calcForm.querySelector('[data-calc="qty"]');
        const rate = calcForm.querySelector('[data-calc="rate"]');
        const amount = calcForm.querySelector('[data-calc="amount"]');
        const unit = calcForm.querySelector('[data-calc="unit"]');
        if (!qty || !rate || !amount) { return; }
        const rateLabel = calcForm.querySelector('label[for="' + rate.id + '"]');
        // اگر کاربر خودش کرایه را نوشت، دیگر خودکار عوض نمی‌شود (تا خالی کند)
        let auto = amount.value.trim() === '';
        amount.addEventListener('input', function () { auto = amount.value.trim() === ''; });
        const recalc = function () {
            const q = toNumber(qty.value);
            const r = toNumber(rate.value);
            if (auto && q !== null && r !== null) {
                amount.value = String(Math.round(q * r));
            }
        };
        qty.addEventListener('input', recalc);
        rate.addEventListener('input', recalc);
        if (unit) {
            unit.addEventListener('change', function () {
                if (rateLabel) {
                    rateLabel.textContent = rateLabel.getAttribute(unit.value === 'kg' ? 'data-label-kg' : 'data-label-seer');
                }
                recalc();
            });
        }
    });

    /* ---------- عدد به حروف (ت۵۸): همان قاعده‌ی Num::words در PHP ----------
       HK.words(12500) ← «دوازده هزار و پنجصد»؛ عدد اعشاری، منفی یا خیلی بزرگ ← ''.
       خانه‌ی مبلغ با data-words: زیر خانه، عدد با جداکننده و به حروف نشان داده می‌شود (و برای ۱ لک به بالا، به لک)
       تا کاربر صفر کم یا زیاد را همان وقت ببیند. data-words="AFN" نام پول را هم می‌نویسد. */
    const words = function (n) {
        n = Number(n);
        if (!isFinite(n) || n < 0 || Math.floor(n) !== n || n > 999999999999) { return ''; }
        const ones = t('words_ones').split('|');
        if (n === 0) { return ones[0]; }
        const tens = t('words_tens').split('|');
        const hundreds = t('words_hundreds').split('|');
        const scales = t('words_scales').split('|');
        const and = t('words_and');
        const out = [];
        const groups = [];
        for (let x = n; x > 0; x = Math.floor(x / 1000)) { groups.push(x % 1000); }
        for (let i = groups.length - 1; i >= 0; i--) {
            const g = groups[i];
            if (g === 0) { continue; }
            const parts = [];
            if (g >= 100) { parts.push(hundreds[Math.floor(g / 100)]); }
            const r = g % 100;
            if (r > 0 && r < 20) {
                parts.push(ones[r]);
            } else if (r >= 20) {
                parts.push(tens[Math.floor(r / 10)]);
                if (r % 10 > 0) { parts.push(ones[r % 10]); }
            }
            out.push(parts.join(and) + (scales[i] ? ' ' + scales[i] : ''));
        }
        return out.join(and);
    };
    window.HK.words = words;
    const faDigits = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(Number(d)); }); };
    /** متن کمکی زیر خانه‌ی مبلغ: «۱۲۵٬۰۰۰ — یکصد و بیست و پنج هزار افغانی (۱٫۲۵ لک)» */
    const wordsLine = function (value, currency) {
        const n = toNumber(value);
        if (n === null || n < 1000) { return ''; }
        const w = words(n);
        if (w === '') { return ''; }
        const grouped = faDigits(String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '٬'));
        const lakh = n >= 100000 ? ' (' + faDigits(String(Math.round(n / 1000) / 100).replace('.', '٫')) + ' ' + t('words_lakh') + ')' : '';
        return grouped + ' — ' + w + (currency ? ' ' + t('currency_' + currency) : '') + lakh;
    };
    window.HK.wordsLine = wordsLine;
    document.querySelectorAll('input[data-words]').forEach(function (input) {
        const line = document.createElement('div');
        line.className = 'form-text hk-words';
        line.setAttribute('aria-live', 'polite');
        const anchor = input.closest('.input-group') || input;
        anchor.insertAdjacentElement('afterend', line);
        const refresh = function () { line.textContent = wordsLine(input.value, input.getAttribute('data-words')); };
        input.addEventListener('input', refresh);
        input.addEventListener('change', refresh);
        // دکمه‌هایی مثل «همه‌ی قرض» مقدار را با کد عوض می‌کنند؛ بعد از هر کلیک در همان فورم دوباره حساب شود
        if (input.form) { input.form.addEventListener('click', function () { window.setTimeout(refresh, 0); }); }
        refresh();
    });

    /* ---------- نمایش عدد با ارقام فارسی ---------- */
    const faNumber = function (n) {
        try {
            return Number(n).toLocaleString('fa-AF', { maximumFractionDigits: 2 });
        } catch (e) {
            return String(n);
        }
    };

    /* ---------- «سطر بیشتر» (button data-add-row="#table"): آخرین سطر کاپی و خالی می‌شود ---------- */
    document.addEventListener('click', function (ev) {
        const button = ev.target.closest('[data-add-row]');
        if (!button) { return; }
        addRow(document.querySelector(button.getAttribute('data-add-row')), true);
    });

    /** یک سطر خالی به آخر جدول؛ رویداد «hk:row-added» روی سطر تازه. (lines.js هم از این استفاده می‌کند) */
    function addRow(table, focus) {
        const body = table && table.tBodies[0];
        if (!body || !body.rows.length) { return null; }
        const index = body.rows.length;
        const row = body.rows[body.rows.length - 1].cloneNode(true);
        row.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/\[\d+\]/, '[' + index + ']');
            if (el.id) { el.id = el.id.replace(/\d+$/, String(index)); }
            if (el.type === 'checkbox') { el.checked = false; } else if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = ''; }
            el.classList.remove('is-invalid');
            el.removeAttribute('placeholder');
        });
        row.querySelectorAll('label[for]').forEach(function (l) { l.htmlFor = l.htmlFor.replace(/\d+$/, String(index)); });
        row.querySelectorAll('.invalid-feedback').forEach(function (f) { f.remove(); });
        const no = row.querySelector('[data-row-no]');
        if (no) { no.textContent = faNumber(index + 1); }
        const sum = row.querySelector('[data-line="sum"]');
        if (sum) { sum.textContent = ''; }
        body.appendChild(row);
        row.dispatchEvent(new CustomEvent('hk:row-added', { bubbles: true }));
        const first = row.querySelector('input:not([type="checkbox"])');
        if (first && focus) { first.focus(); }
        return row;
    }
    window.HK.addRow = addRow;

    /* ---------- جمع هر سطر و جمع کل (table data-line-totals؛ data-line="qty|rate|sum") ---------- */
    document.querySelectorAll('table[data-line-totals]').forEach(function (table) {
        const grand = document.querySelector(table.getAttribute('data-grand-total'));
        const update = function () {
            let total = 0;
            table.querySelectorAll('tbody tr').forEach(function (tr) {
                const q = toNumber((tr.querySelector('[data-line="qty"]') || {}).value);
                const r = toNumber((tr.querySelector('[data-line="rate"]') || {}).value);
                const cell = tr.querySelector('[data-line="sum"]');
                const s = q !== null && r !== null ? Math.round(q * r * 100) / 100 : null;
                if (cell) { cell.textContent = s === null ? '' : faNumber(s); }
                if (s !== null) { total += s; }
            });
            if (grand) { grand.textContent = faNumber(Math.round(total * 100) / 100); }
        };
        table.addEventListener('input', update);
        update();
    });

    /* ---------- فورم ورق خرید: نرخ فقط برای دالر و کلدار؛ نرخ روز پیشنهاد ---------- */
    const purchaseForm = document.querySelector('form[data-purchase-form]');
    if (purchaseForm) {
        let rates = {};
        try { rates = JSON.parse(purchaseForm.getAttribute('data-rates') || '{}'); } catch (e) { rates = {}; }
        const supplier = purchaseForm.querySelector('[data-supplier]');
        const newName = purchaseForm.querySelector('[data-new-supplier]');
        const newCurrency = purchaseForm.querySelector('[data-new-currency]');
        const group = purchaseForm.querySelector('[data-rate-group]');
        const rateInput = purchaseForm.querySelector('[data-rate-input]');
        const hint = purchaseForm.querySelector('[data-rate-hint]');
        const labels = purchaseForm.querySelectorAll('[data-currency-label]');
        const currencyNames = (window.HK && window.HK.t) ? window.HK.t : function (k) { return k; };
        const currentCurrency = function () {
            if (supplier && supplier.value) { return supplier.options[supplier.selectedIndex].getAttribute('data-currency') || 'AFN'; }
            if (newName && newName.value.trim() !== '' && newCurrency) { return newCurrency.value; }
            return '';
        };
        const refresh = function () {
            const code = currentCurrency();
            const foreign = code !== '' && code !== 'AFN';
            if (group) { group.classList.toggle('d-none', !foreign); }
            if (foreign && rateInput && rateInput.value.trim() === '' && rates[code] && rates[code].rate) { rateInput.value = rates[code].rate; }
            if (hint && foreign && rates[code]) { hint.textContent = hint.textContent.replace(/[0-9۰-۹,٬]+/, faNumber(rates[code].unit)); }
            labels.forEach(function (l) { l.textContent = code ? '(' + currencyNames('currency_' + code) + ')' : ''; });
        };
        [supplier, newName, newCurrency].forEach(function (el) { if (el) { el.addEventListener('change', refresh); el.addEventListener('input', refresh); } });
        refresh();
    }

    /* ---------- فورم دریافت پول قرض (data-payment-form): قرض فعلی شخص، «همه‌ی قرض»، قرض بعد از این پول ---------- */
    const payForm = document.querySelector('form[data-payment-form]');
    if (payForm) {
        const party = payForm.querySelector('[data-payment-party]');
        const amount = payForm.querySelector('[data-payment-amount]');
        const after = payForm.querySelector('[data-payment-after]');
        const kinds = payForm.querySelectorAll('[data-payment-kind]');
        const balanceOf = function (kind) {
            const opt = party.options[party.selectedIndex];
            return opt && opt.value ? Number(opt.getAttribute('data-' + kind) || 0) : 0;
        };
        const currentKind = function () {
            const c = Array.prototype.find.call(kinds, function (k) { return k.checked; });
            return c ? c.value : 'shop';
        };
        const refreshPay = function () {
            payForm.querySelectorAll('[data-payment-balance]').forEach(function (s) {
                const b = balanceOf(s.getAttribute('data-payment-balance'));
                s.textContent = party.value ? '(' + faNumber(b) + ')' : '';
            });
            // اگر فقط یک نوع قرض دارد، همان انتخاب شود
            if (party.value && balanceOf(currentKind()) <= 0) {
                kinds.forEach(function (k) { if (balanceOf(k.value) > 0) { k.checked = true; } });
            }
            const debt = balanceOf(currentKind());
            const paid = toNumber(amount.value);
            if (party.value && paid !== null && paid > 0) {
                after.textContent = t('pay_after').replace(':debt', faNumber(debt)).replace(':paid', faNumber(paid)).replace(':left', faNumber(Math.round((debt - paid) * 100) / 100));
                after.hidden = false;
            } else {
                after.hidden = true;
            }
        };
        party.addEventListener('change', refreshPay);
        kinds.forEach(function (k) { k.addEventListener('change', refreshPay); });
        amount.addEventListener('input', refreshPay);
        const all = payForm.querySelector('[data-payment-all]');
        if (all) { all.addEventListener('click', function () { amount.value = String(balanceOf(currentKind())); refreshPay(); }); }
        refreshPay();
    }

    /* ---------- دریافت پول: نقد یا حواله (data-method) ← خانه‌های حواله (data-transfer-only) — ۲-۳ ---------- */
    const methods = document.querySelectorAll('input[data-method]');
    if (methods.length) {
        const showTransfer = function () {
            const on = Array.prototype.some.call(methods, function (m) { return m.checked && m.value === 'transfer'; });
            document.querySelectorAll('[data-transfer-only]').forEach(function (el) { el.hidden = !on; });
        };
        methods.forEach(function (m) { m.addEventListener('change', showTransfer); });
        showTransfer();
    }

    /* ---------- فورم‌های فیلتر (method=get): با عوض کردن لیست، تاریخ یا تیک، همان وقت فیلتر شود ----------
       (کاربر انتظار دارد بعد از انتخاب، لیست عوض شود؛ دکمه‌ی «جستجو» هم کار می‌کند؛ جستجوی متن با Enter)
       فورمی که data-no-auto دارد یا خودش این کار را می‌کند (data-closing-reload) جدا است. */
    document.querySelectorAll('form').forEach(function (f) {
        if ((f.getAttribute('method') || '').toLowerCase() !== 'get' || f.hasAttribute('data-no-auto') || f.querySelector('[data-closing-reload]')) {
            return;
        }
        let sent = false;
        f.addEventListener('change', function (ev) {
            const el = ev.target;
            const auto = el.matches('select, input[type="checkbox"], input[type="radio"], input[data-jdate]');
            if (!auto || sent) { return; }
            // تاریخ نیمه‌نوشته فرستاده نشود
            if (el.matches('input[data-jdate]') && el.value.trim() !== '' && !/^[0-9۰-۹]{4}[\/\-.][0-9۰-۹]{1,2}[\/\-.][0-9۰-۹]{1,2}$/.test(el.value.trim())) { return; }
            sent = true;
            if (typeof f.requestSubmit === 'function') { f.requestSubmit(); } else { f.submit(); }
        });
    });

    /* ---------- رسید بار: عوض کردن روش تقسیم، پایه‌ی تقسیم یا واحد وزن ← همان وقت «حساب کن» (data-recompute) ---------- */
    document.querySelectorAll('[data-recompute]').forEach(function (el) {
        el.addEventListener('change', function () {
            const f = el.form;
            const btn = f && f.querySelector('button[name="preview"]');
            if (!btn) { return; }
            if (typeof f.requestSubmit === 'function') { f.requestSubmit(btn); } else { btn.click(); }
        });
    });

    /* ---------- قیمت واحدهای بزرگ‌تر خودکار از قیمت واحد اصلی (data-price-group) ----------
       قیمت‌ها (پرچون/عمده) و فورم جنس: با نوشتن قیمت واحد اصلی، خانه‌ی هر واحد بزرگ‌تر = قیمت اصلی × ضریب.
       خانه‌ای که دستی عوض شود دیگر خودکار نمی‌شود؛ خانه‌ای که خالی است یا همان «اصلی × ضریب» است، پیرو می‌ماند.
       هنگام ذخیره: خانه‌ی پیرو که پیش از این قیمت جدا نداشت خالی فرستاده می‌شود (سرور همان «اصلی × ضریب» را حساب می‌کند،
       تا با عوض شدن قیمت اصلی — مثلاً در رسید بار — خودش عوض شود)؛ خانه‌ی پیرو که قیمت جدا داشت، عدد تازه را می‌فرستد. */
    function priceNum(s) {
        const v = String(s || '').trim().replace(/[۰-۹]/g, function (d) { return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)); })
            .replace(/[٠-٩]/g, function (d) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)); }).replace(/[٬,\s]/g, '').replace('٫', '.');
        if (v === '' || !/^\d*\.?\d+$/.test(v)) { return null; }
        return parseFloat(v);
    }
    function priceFmt(x) { return String(Math.round(x * 100) / 100); }
    document.querySelectorAll('[data-price-group]').forEach(function (group) {
        const base = group.querySelector('input[data-price-base]');
        if (!base) { return; }
        const factorOf = function (inp) {
            if (inp.dataset.factor) { return priceNum(inp.dataset.factor); }
            const row = inp.closest('[data-price-row]');
            const f = row && row.querySelector('[data-factor-input]');
            return f ? priceNum(f.value) : null;
        };
        const followers = function () { return group.querySelectorAll('input[data-price-follow]'); };
        // پیرو در شروع: خالی، یا برابر «اصلی × ضریب»
        const b0 = priceNum(base.value);
        followers().forEach(function (o) {
            const f = factorOf(o);
            const v = priceNum(o.value);
            o.dataset.hadOwn = o.value.trim() === '' ? '0' : '1';
            o.dataset.following = (o.value.trim() === '' || (b0 !== null && f !== null && v !== null && Math.abs(v - b0 * f) < 0.01)) ? '1' : '0';
            o.addEventListener('input', function () { o.dataset.following = o.value.trim() === '' ? '1' : '0'; o.classList.remove('hk-auto'); });
        });
        const fill = function () {
            const b = priceNum(base.value);
            followers().forEach(function (o) {
                if (o.dataset.following !== '1') { return; }
                const f = factorOf(o);
                o.value = (b === null || f === null) ? '' : priceFmt(b * f);
                o.classList.toggle('hk-auto', o.value !== '');
            });
        };
        fill(); // نمایش «اصلی × ضریب» از همان اول (بی تغییر در «ذخیره‌نشده»)
        base.addEventListener('input', fill);
        group.querySelectorAll('[data-factor-input]').forEach(function (f) { f.addEventListener('input', fill); });
        const form = group.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                followers().forEach(function (o) {
                    if (o.dataset.following === '1' && o.dataset.hadOwn !== '1') { o.value = ''; }
                });
            });
        }
    });

    /* ---------- فورم با تغییر ذخیره‌نشده (data-guard-unsaved) — مثلاً قیمت‌ها: پیش از رفتن به صفحه‌ی دیگر بپرسد ---------- */
    document.querySelectorAll('form[data-guard-unsaved]').forEach(function (f) {
        let dirty = false;
        f.addEventListener('input', function () { dirty = true; });
        f.addEventListener('submit', function () { dirty = false; });
        document.addEventListener('click', function (e) {
            const a = e.target.closest('a[href]');
            if (!dirty || !a || a.getAttribute('href').charAt(0) === '#') { return; }
            if (!window.confirm(t('unsaved_leave'))) { e.preventDefault(); }
        });
    });

    /* ---------- دکمه‌ی چاپ صفحه (data-print) — گزارش‌ها ---------- */
    document.querySelectorAll('[data-print]').forEach(function (b) {
        b.addEventListener('click', function () { window.print(); });
    });

    /* ---------- فورم پرداخت به عمده‌فروش (data-supplier-pay): فقط صندوق‌های هم‌پول، «همه‌ی قرض»، قرض بعد از پرداخت ---------- */
    const spForm = document.querySelector('form[data-supplier-pay]');
    if (spForm) {
        const sup = spForm.querySelector('[data-sp-supplier]');
        const cash = spForm.querySelector('[data-sp-cash]');
        const spAmount = spForm.querySelector('[data-sp-amount]');
        const spAfter = spForm.querySelector('[data-sp-after]');
        const spCur = spForm.querySelector('[data-sp-currency]');
        const selected = function () { return sup.value ? sup.options[sup.selectedIndex] : null; };
        const refreshSp = function () {
            const opt = selected();
            const code = opt ? opt.getAttribute('data-currency') : '';
            spCur.textContent = code ? '(' + t('currency_' + code) + ')' : '';
            // صندوق‌های پول دیگر پنهان؛ اگر صندوق انتخاب‌شده هم‌پول نیست، خالی شود؛ اگر فقط یکی بود، خودش انتخاب شود
            const same = [];
            Array.prototype.forEach.call(cash.options, function (o) {
                if (!o.value) { return; }
                const ok = !code || o.getAttribute('data-currency') === code;
                o.hidden = !ok;
                o.disabled = !ok;
                if (ok) { same.push(o); }
            });
            if (cash.selectedIndex > 0 && cash.options[cash.selectedIndex].disabled) { cash.value = ''; }
            if (code && same.length === 1 && !cash.value) { cash.value = same[0].value; }
            const debt = opt ? Number(opt.getAttribute('data-balance') || 0) : 0;
            const paid = toNumber(spAmount.value);
            if (opt && paid !== null && paid > 0) {
                spAfter.textContent = t('sp_after').replace(':debt', faNumber(debt)).replace(':paid', faNumber(paid)).replace(':left', faNumber(Math.round((debt - paid) * 100) / 100));
                spAfter.hidden = false;
            } else {
                spAfter.hidden = true;
            }
        };
        sup.addEventListener('change', refreshSp);
        spAmount.addEventListener('input', refreshSp);
        const spAll = spForm.querySelector('[data-sp-all]');
        if (spAll) { spAll.addEventListener('click', function () { const o = selected(); if (o) { spAmount.value = String(Number(o.getAttribute('data-balance') || 0)); refreshSp(); } }); }
        refreshSp();
    }

    /* ---------- فورم شخص: خانه‌های مخصوص یک نقش (data-role-show="supplier") ---------- */
    const roleBoxes = document.querySelectorAll('input[name="roles[]"]');
    if (roleBoxes.length && document.querySelector('[data-role-show]')) {
        const toggleRoles = function () {
            const checked = Array.prototype.filter.call(roleBoxes, function (b) { return b.checked; }).map(function (b) { return b.value; });
            document.querySelectorAll('[data-role-show]').forEach(function (el) {
                el.classList.toggle('d-none', checked.indexOf(el.getAttribute('data-role-show')) === -1);
            });
        };
        roleBoxes.forEach(function (b) { b.addEventListener('change', toggleRoles); });
        toggleRoles();
    }

    /* =====================================================================
       تقویم شمسی (ت۲۳) — روی هر خانه‌ی تاریخ (input data-jdate).
       با کلیک روی خانه باز می‌شود؛ نوشتن دستی هم ممکن است. تاریخ به شکل ۱۴۰۵/۰۸/۲۱ نوشته می‌شود
       (همان شکلی که سرور می‌خواند). هفته از شنبه شروع می‌شود؛ جمعه سرخ.
       الگوریتم همان الگوریتم کلاس Jalali در PHP است (روش ۳۳ساله).
       ===================================================================== */
    const toFaDigits = function (s) {
        return String(s).replace(/[0-9]/g, function (d) { return String.fromCharCode(0x06F0 + Number(d)); });
    };
    const toEnDigits = function (s) {
        return String(s || '')
            .replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
            .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); });
    };
    const div = function (a, b) { return Math.floor(a / b); };
    const g2j = function (gy, gm, gd) {
        const before = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        const gy2 = gm > 2 ? gy + 1 : gy;
        let days = 355666 + 365 * gy + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) + gd + before[gm - 1];
        let jy = -1595 + 33 * div(days, 12053);
        days %= 12053;
        jy += 4 * div(days, 1461);
        days %= 1461;
        if (days > 365) { jy += div(days - 1, 365); days = (days - 1) % 365; }
        return days < 186
            ? [jy, 1 + div(days, 31), 1 + (days % 31)]
            : [jy, 7 + div(days - 186, 30), 1 + ((days - 186) % 30)];
    };
    const j2g = function (jy, jm, jd) {
        jy += 1595;
        let days = -355668 + 365 * jy + div(jy, 33) * 8 + div((jy % 33) + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
        let gy = 400 * div(days, 146097);
        days %= 146097;
        if (days > 36524) {
            days--;
            gy += 100 * div(days, 36524);
            days %= 36524;
            if (days >= 365) { days++; }
        }
        gy += 4 * div(days, 1461);
        days %= 1461;
        if (days > 365) { gy += div(days - 1, 365); days = (days - 1) % 365; }
        let gd = days + 1;
        const leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
        const md = [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        let gm = 0;
        while (gm < 12 && gd > md[gm]) { gd -= md[gm]; gm++; }
        return [gy, gm + 1, gd];
    };
    const jDaysInMonth = function (jy, jm) {
        if (jm <= 6) { return 31; }
        if (jm <= 11) { return 30; }
        const g = j2g(jy, 12, 30);
        const back = g2j(g[0], g[1], g[2]);
        return back[0] === jy && back[1] === 12 && back[2] === 30 ? 30 : 29;
    };
    // امروز از سرور (ساعت کابل)، نه از ساعت مرورگر
    const todayJ = (function () {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(meta('hk-today'));
        const d = m ? null : new Date();
        return m ? g2j(Number(m[1]), Number(m[2]), Number(m[3])) : g2j(d.getFullYear(), d.getMonth() + 1, d.getDate());
    })();
    const parseJ = function (value) {
        const m = /^\s*(\d{2,4})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\s*$/.exec(toEnDigits(value));
        if (!m) { return null; }
        let y = Number(m[1]);
        if (y < 100) { y += 1400; }
        const mo = Number(m[2]);
        const d = Number(m[3]);
        return mo >= 1 && mo <= 12 && d >= 1 && d <= jDaysInMonth(y, mo) ? [y, mo, d] : null;
    };
    const pad2 = function (n) { return n < 10 ? '0' + n : String(n); };

    let picker = null;   // پنجره‌ی باز: {el, input, y, m}
    const closePicker = function () {
        if (picker) { picker.el.remove(); picker = null; }
    };
    const renderPicker = function () {
        const p = picker;
        const months = t('jdate_months');
        const weekdays = t('jdate_weekdays');
        const chosen = parseJ(p.input.value);
        p.el.textContent = '';

        const head = document.createElement('div');
        head.className = 'jdp-head';
        const nav = function (step, icon, label) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'jdp-nav';
            b.title = label;
            b.setAttribute('aria-label', label);
            b.setAttribute('data-jdp-step', String(step));
            const i = document.createElement('i');
            i.className = 'fa-solid ' + icon;
            b.appendChild(i);
            return b;
        };
        // در راست‌به‌چپ «قبل» سمت راست است
        head.appendChild(nav(-12, 'fa-angles-right', t('jdate_prev_year')));
        head.appendChild(nav(-1, 'fa-angle-right', t('jdate_prev_month')));
        const title = document.createElement('div');
        title.className = 'jdp-title';
        title.textContent = (Array.isArray(months) ? months[p.m - 1] : p.m) + ' ' + toFaDigits(p.y);
        head.appendChild(title);
        head.appendChild(nav(1, 'fa-angle-left', t('jdate_next_month')));
        head.appendChild(nav(12, 'fa-angles-left', t('jdate_next_year')));
        p.el.appendChild(head);

        const grid = document.createElement('div');
        grid.className = 'jdp-grid';
        (Array.isArray(weekdays) ? weekdays : ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']).forEach(function (w, i) {
            const c = document.createElement('div');
            c.className = 'jdp-wd' + (i === 6 ? ' fri' : '');
            c.textContent = w;
            grid.appendChild(c);
        });
        const g = j2g(p.y, p.m, 1);
        const offset = (new Date(g[0], g[1] - 1, g[2]).getDay() + 1) % 7;   // شنبه = ۰
        for (let i = 0; i < offset; i++) { grid.appendChild(document.createElement('span')); }
        const n = jDaysInMonth(p.y, p.m);
        for (let d = 1; d <= n; d++) {
            const b = document.createElement('button');
            b.type = 'button';
            let cls = 'jdp-day';
            if ((offset + d - 1) % 7 === 6) { cls += ' fri'; }
            if (p.y === todayJ[0] && p.m === todayJ[1] && d === todayJ[2]) { cls += ' today'; }
            if (chosen && chosen[0] === p.y && chosen[1] === p.m && chosen[2] === d) { cls += ' sel'; }
            b.className = cls;
            b.textContent = toFaDigits(d);
            b.setAttribute('data-jdp-day', String(d));
            grid.appendChild(b);
        }
        p.el.appendChild(grid);

        const foot = document.createElement('div');
        foot.className = 'jdp-foot';
        const todayBtn = document.createElement('button');
        todayBtn.type = 'button';
        todayBtn.className = 'btn btn-sm btn-outline-primary';
        todayBtn.setAttribute('data-jdp-today', '1');
        todayBtn.textContent = t('jdate_today');
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn btn-sm btn-light';
        closeBtn.setAttribute('data-jdp-close', '1');
        closeBtn.textContent = t('jdate_close');
        foot.appendChild(todayBtn);
        foot.appendChild(closeBtn);
        p.el.appendChild(foot);
    };
    const placePicker = function () {
        if (!picker) { return; }
        const r = picker.input.getBoundingClientRect();
        const w = picker.el.offsetWidth;
        let left = r.right + window.scrollX - w;
        left = Math.max(window.scrollX + 8, Math.min(left, window.scrollX + document.documentElement.clientWidth - w - 8));
        picker.el.style.left = left + 'px';
        picker.el.style.top = (r.bottom + window.scrollY + 4) + 'px';
    };
    const openPicker = function (input) {
        if (picker && picker.input === input) { return; }
        closePicker();
        const start = parseJ(input.value) || todayJ;
        const el = document.createElement('div');
        el.className = 'jdp';
        el.setAttribute('role', 'dialog');
        // کلیک داخل پنجره، تمرکز را از خانه‌ی تاریخ نمی‌گیرد
        el.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        el.addEventListener('click', function (ev) {
            const b = ev.target.closest('button');
            if (!b || !picker) { return; }
            if (b.hasAttribute('data-jdp-step')) {
                const step = Number(b.getAttribute('data-jdp-step'));
                const total = picker.y * 12 + (picker.m - 1) + step;
                picker.y = div(total, 12);
                picker.m = (total % 12) + 1;
                renderPicker();
                return;
            }
            if (b.hasAttribute('data-jdp-close')) { closePicker(); return; }
            const day = b.hasAttribute('data-jdp-today') ? todayJ : (b.hasAttribute('data-jdp-day') ? [picker.y, picker.m, Number(b.getAttribute('data-jdp-day'))] : null);
            if (!day) { return; }
            const target = picker.input;
            target.value = toFaDigits(day[0] + '/' + pad2(day[1]) + '/' + pad2(day[2]));
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            closePicker();
            target.focus();
        });
        document.body.appendChild(el);
        picker = { el: el, input: input, y: start[0], m: start[1] };
        renderPicker();
        placePicker();
    };
    document.querySelectorAll('input[data-jdate]').forEach(function (input) {
        input.classList.add('jdate-input');
        if (!input.title) { input.title = t('jdate_hint'); }
        input.addEventListener('click', function () { openPicker(input); });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowDown' && ev.altKey) { ev.preventDefault(); openPicker(input); }
            if (ev.key === 'Escape' || ev.key === 'Tab' || ev.key === 'Enter') { closePicker(); }
        });
        // اگر تاریخ دستی نوشته شد، تقویم همان ماه را نشان دهد
        input.addEventListener('input', function () {
            const v = parseJ(input.value);
            if (picker && picker.input === input && v) { picker.y = v[0]; picker.m = v[1]; renderPicker(); }
        });
    });
    document.addEventListener('mousedown', function (ev) {
        if (picker && !picker.el.contains(ev.target) && ev.target !== picker.input) { closePicker(); }
    });
    window.addEventListener('resize', placePicker);

    /* =====================================================================
       خانه‌های عددی (inputmode=decimal یا numeric):
       - placeholder با «فقط عدد» شروع می‌شود (اگر نبود، اضافه می‌شود)
       - اگر حرف نوشته شود، زیر خانه یک هشدار سرخ دیده می‌شود (ارقام فارسی و انگلیسی، نقطه، کامه قبول است)
       ===================================================================== */
    const numericSelector = 'input[inputmode="decimal"], input[inputmode="numeric"]';
    const numberOnly = t('number_only');
    document.querySelectorAll(numericSelector).forEach(function (input) {
        const ph = input.getAttribute('placeholder') || '';
        if (ph === '') {
            input.setAttribute('placeholder', numberOnly);
        } else if (ph.indexOf(numberOnly) === -1) {
            input.setAttribute('placeholder', numberOnly + '، ' + ph);
        }
    });
    document.addEventListener('input', function (ev) {
        const input = ev.target;
        if (!input.matches || !input.matches(numericSelector)) { return; }
        const bad = /[^0-9۰-۹٠-٩.,٬٫،\s\/\-+]/.test(input.value);
        let warn = input.nextElementSibling && input.nextElementSibling.classList.contains('num-warn') ? input.nextElementSibling : null;
        if (bad && !warn) {
            warn = document.createElement('div');
            warn.className = 'num-warn';
            warn.textContent = t('number_only_warn');
            input.insertAdjacentElement('afterend', warn);
        } else if (!bad && warn) {
            warn.remove();
        }
    });

    /* =====================================================================
       نمودار ستونی (partials/bar_chart.php): با موس یا Tab روی هر دوره، همه‌ی عددهای آن دوره.
       متن‌ها با textContent گذاشته می‌شوند (نه innerHTML). عددها در جدول زیر نمودار هم هست.
       ===================================================================== */
    const showTip = function (group) {
        const plot = group.closest('.viz-plot');
        const tip = plot && plot.querySelector('.viz-tip');
        if (!tip) { return; }
        let data;
        try { data = JSON.parse(group.getAttribute('data-viz-tip') || '{}'); } catch (e) { return; }
        tip.textContent = '';
        const title = document.createElement('div');
        title.className = 'viz-tip-title';
        title.textContent = data.title || '';
        tip.appendChild(title);
        (data.rows || []).forEach(function (r) {
            const row = document.createElement('div');
            row.className = 'viz-tip-row';
            const key = document.createElement('i');
            key.className = 'viz-tip-key ' + (r[2] || '');
            const value = document.createElement('b');
            value.textContent = r[1];
            const label = document.createElement('span');
            label.textContent = r[0];
            row.appendChild(key);
            row.appendChild(value);
            row.appendChild(label);
            tip.appendChild(row);
        });
        tip.hidden = false;
        // جای tooltip: بالای ستون‌های همان دوره، داخل کادر نمودار
        const hit = group.querySelector('.viz-hit').getBoundingClientRect();
        const box = plot.getBoundingClientRect();
        const w = tip.offsetWidth;
        let left = hit.left - box.left + hit.width / 2 - w / 2;
        left = Math.max(0, Math.min(left, box.width - w));
        tip.style.left = left + 'px';
        tip.style.top = Math.max(0, hit.top - box.top - 4) + 'px';
    };
    const hideTip = function (group) {
        const tip = group.closest('.viz-plot') && group.closest('.viz-plot').querySelector('.viz-tip');
        if (tip) { tip.hidden = true; }
    };
    document.querySelectorAll('.viz-group[data-viz-tip]').forEach(function (g) {
        g.addEventListener('pointerenter', function () { showTip(g); });
        g.addEventListener('pointerleave', function () { hideTip(g); });
        g.addEventListener('focus', function () { showTip(g); });
        g.addEventListener('blur', function () { hideTip(g); });
    });

    /* ---------- فورم «آزمایش ابزارها» در صفحه‌ی وضعیت سیستم ---------- */
    const form = document.getElementById('hk-check-form');
    if (form) {
        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const out = document.getElementById('hk-check-result');
            out.textContent = '';
            const status = document.createElement('dd');
            status.textContent = t('checking');
            out.appendChild(status);

            const query = new URLSearchParams(new FormData(form)).toString();
            try {
                const result = await api('/system/check-input?' + query);
                out.textContent = '';
                if (!result.rows.length) {
                    status.textContent = t('no_result');
                    out.appendChild(status);
                    return;
                }
                result.rows.forEach(function (row) {
                    const dt = document.createElement('dt');
                    dt.textContent = row.label;
                    const dd = document.createElement('dd');
                    dd.textContent = row.value;
                    out.appendChild(dt);
                    out.appendChild(dd);
                });
            } catch (e) {
                out.textContent = '';
                status.textContent = e.message;
                out.appendChild(status);
            }
        });
    }
})();
