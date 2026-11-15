<?php

return [
    'title' => 'ثبت رویدادها',
    'time' => 'زمان',
    'user' => 'کاربر',
    'action' => 'رویداد',
    'description' => 'شرح',
    'changes' => 'تغییرات',
    'ip' => 'دستگاه (IP)',
    'old' => 'قبلی',
    'new' => 'جدید',
    'system' => 'سیستم',
    'groups' => [
        'auth' => 'ورود و خروج',
        'user' => 'کاربران',
    ],
    'actions' => [
        'auth' => [
            'login' => 'ورود',
            'logout' => 'خروج',
            'failed' => 'ورود ناموفق',
            'password_changed' => 'تغییر رمز',
        ],
        'user' => [
            'created' => 'ساخت کاربر',
            'updated' => 'ویرایش کاربر',
            'activated' => 'فعال کردن کاربر',
            'deactivated' => 'غیرفعال کردن کاربر',
            'password_reset' => 'نو کردن رمز کاربر',
        ],
    ],
];
