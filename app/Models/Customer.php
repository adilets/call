<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'customers';

    protected $fillable = [
        'client_id',
        'user_id',
        'name',
        'first_name',
        'last_name',
        'email',
        'photo',
        'gender',
        'phone',
        'birthday'
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'birthday' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $first = trim((string) $customer->first_name);
            $last  = trim((string) $customer->last_name);

            if ($first !== '' || $last !== '') {
                $customer->name = trim($first . ' ' . $last);
            }
        });
    }

    /** @return HasMany<Order> */
    public function comments(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return MorphToMany<Address> */
    public function addresses(): MorphToMany
    {
        return $this->morphToMany(Address::class, 'addressable');
    }

    /** @return HasManyThrough<Payment> */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Order::class, 'customer_id');
    }
}
