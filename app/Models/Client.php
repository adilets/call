<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Client extends Model
{
    protected $fillable = ['name', 'company', 'phone', 'path', 'currencies', 'countries'];

    protected $casts = [
        'currencies' => 'array',
        'countries' => 'array',
    ];

    public function users(): HasMany {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany {
        return $this->hasMany(Product::class);
    }

    /** @return BelongsToMany<PaymentMethod> */
    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class, 'client_payment_method');
    }
}
