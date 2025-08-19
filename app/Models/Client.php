<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'company', 'phone', 'path'];

    public function users(): HasMany {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany {
        return $this->hasMany(Product::class);
    }
}
