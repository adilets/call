<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingMethodCountry extends Model
{
    protected $table = 'shipping_method_country';

    public $timestamps = false;

    protected $fillable = ['shipping_method_id', 'country_code'];

    public function shippingMethod(): BelongsTo {
        return $this->belongsTo(ShippingMethod::class);
    }
}
