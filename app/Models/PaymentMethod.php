<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
        'sort_order',
    ];

    /** @return BelongsToMany<Client> */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_payment_method');
    }
}


