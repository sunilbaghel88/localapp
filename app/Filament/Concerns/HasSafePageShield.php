<?php

namespace App\Filament\Concerns;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Facades\Filament;

trait HasSafePageShield
{
    use HasPageShield;

    public static function canAccess(array $parameters = []): bool
    {
        $user = Filament::auth()->user();

        return $user?->can(static::getPermissionName()) ?? false;
    }
}
