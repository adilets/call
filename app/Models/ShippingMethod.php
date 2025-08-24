<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'shipping_methods';

    protected $fillable = [
        'client_id',
        'name',
        'type',
        'cost',
        'enabled',
        'description',
        'user_id'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'cost' => 'float',
    ];

    public function client(): BelongsTo {
        return $this->belongsTo(Client::class);
    }

    public function countries(): HasMany {
        return $this->hasMany(ShippingMethodCountry::class);
    }
}
