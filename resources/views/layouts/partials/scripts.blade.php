{{-- متن‌های JavaScript (app.js این را می‌خواند). نوع application/json اجرا نمی‌شود، پس با CSP مشکلی ندارد. --}}
<script type="application/json" id="hk-lang">@json(array_merge(__('js'), ['jdate_months' => __('dates.months'), 'jdate_weekdays' => __('dates.weekdays_short')]))</script>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
