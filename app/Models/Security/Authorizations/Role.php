<?php

namespace App\Models\Security\Authorizations;

use App\Support\Database\DatabaseDialect;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $guard_name = 'web';

    public static function search($query)
    {
        if (empty($query)) {
            return static::query();
        }

        return DatabaseDialect::ilike(static::query(), 'name', '%'.strtoupper($query).'%');
    }

    public function defaultRolePhotoUrl()
    {
        $nombres = trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($nombres).'&background=6875F5&color=f5f5f5'; // color=ff0000&background=a0a0a0
    }

    public static function roleHasPermission($role, $permissions)
    {
        $hasPermission = true;

        foreach ($permissions as $permission) {
            if (! $role->hasPermissionTo($permission->name)) {
                $hasPermission = false;

                return $hasPermission;
            }

            return $hasPermission;
        }
    }
}
