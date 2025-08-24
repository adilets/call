<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait AppliesRoleVisibility
{
    /** Apply where constraints for manager/operator roles. */
    public static function scopeForAuthUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1=0');
        }

        if ($user->hasRole('admin')) {
            return $query;
        }

        if ($user->hasRole('manager')) {
            return $query->where('client_id', $user->client_id);
        }

        if ($user->hasRole('operator')) {
            return $query->where('client_id', $user->client_id)->where('user_id', $user->id);
        }

        return $query->whereRaw('1=0');
    }
}


