<?php

namespace App\Support;

class EnumOptions
{
    /** @param class-string $enumClass */
    public static function for(string $enumClass): array
    {
        return collect($enumClass::cases())
            ->mapWithKeys(fn ($case) => [$case->value => str($case->name)->headline()->toString()])
            ->all();
    }
}
