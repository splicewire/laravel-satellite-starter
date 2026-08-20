<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * `spatie/laravel-permission`'s Role, keyed by uuid to match
 * `laravel-beam-accounts`' `create_permission_tables` migration (a cross-host morph-key convention,
 * ADR — the same reason `beam_access_grants`/`beam_memberships` etc. use string/uuid keys). Spatie's
 * stock model assumes an auto-increment integer PK; `HasUuids` is what actually generates one on
 * create — without it, `Role::findOrCreate()` (used by `TeamProvisioner::syncSpatieRole()`) fails a
 * NOT NULL constraint on `id`. Bound via `config('permission.models.role')`.
 */
class Role extends SpatieRole
{
    use HasUuids;
}
