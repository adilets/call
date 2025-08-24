<?php

namespace App\Filament\Resources\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait AppliesRoleScope
{
    /** Apply role-based visibility (admin → all; manager → by client_id; operator → by client_id + user_id). */
    public static function applyRoleScope(Builder $query): Builder
    {
        $user = Auth::user();

        if (!$user) {
            return $query->whereRaw('1=0');
        }

        if ($user instanceof User && method_exists($user, 'hasRole')) {
            if ($user->hasRole('admin')) {
                return $query;
            }

            // Backward compatibility: treat legacy 'client' as 'manager'
            if ($user->hasRole('manager')) {
                return $query->where('client_id', $user->client_id);
            }

            if ($user->hasRole('operator')) {
                return $query->where('user_id', $user->id);
            }

            // Unknown role → deny
            return $query->whereRaw('1=0');
        }

        // If role system is not available → fallback to requiring auth
        return $query->where('client_id', $user->client_id);
    }
}


