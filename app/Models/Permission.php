<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * `spatie/laravel-permission`'s Permission, keyed by uuid — see {@see Role}'s docblock for the
 * cross-host morph-key rationale and why `HasUuids` is required here.
 */
class Permission extends SpatiePermission
{
    use HasUuids;
}
