/* صفحه‌ی چاپ: دکمه‌ی «چاپ» (data-print). (CSP اجازه‌ی JS داخل صفحه را نمی‌دهد، پس جدا است.) */
(function () {
    'use strict';
    document.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-print]')) {
            window.print();
        }
    });
})();
