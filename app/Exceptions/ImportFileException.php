<?php

namespace App\Exceptions;

use RuntimeException;

/** خطای فایل ورود گروهی که متن آن برای کاربر است (فایل خالی، سطرهای زیاد …). */
class ImportFileException extends RuntimeException {}
