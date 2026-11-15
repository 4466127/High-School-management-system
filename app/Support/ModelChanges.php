<?php

namespace App\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/** مقدار قبلی و جدید فیلدهای تغییرکرده (پس از fill و پیش از save) برای ثبت رویداد. */
final class ModelChanges
{
    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} [قبلی، جدید] */
    public static function capture(Model $model, array $ignore = ['updated_at', 'created_at']): array
    {
        $old = [];
        $new = [];

        foreach (array_keys($model->getDirty()) as $key) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            $old[$key] = self::plain($model->getOriginal($key));
            $new[$key] = self::plain($model->getAttribute($key));
        }

        return [$old, $new];
    }

    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof BackedEnum => $value->value,
            default => $value,
        };
    }
}
