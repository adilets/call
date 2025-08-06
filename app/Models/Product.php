<?php

namespace App\Models;

//use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /**
     * @var string
     */
    protected $table = 'products';

    protected $fillable = [
        'client_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'qty',
        'security_stock',
        'featured',
        'is_visible',
        'old_price',
        'price',
        'cost',
        'type',
        'backorder',
        'requires_shipping',
        'published_at',
        'seo_title',
        'seo_description',
        'weight_value',
        'weight_unit',
        'height_value',
        'height_unit',
        'width_value',
        'width_unit',
        'depth_value',
        'depth_unit',
        'volume_value',
        'volume_unit',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'featured' => 'boolean',
        'is_visible' => 'boolean',
        'backorder' => 'boolean',
        'requires_shipping' => 'boolean',
        'published_at' => 'date',
        'qty' => 'integer',
        'security_stock' => 'integer',
        'old_price' => 'float',
        'price' => 'float',
        'cost' => 'float',
        'weight_value' => 'float',
        'height_value' => 'float',
        'width_value' => 'float',
        'depth_value' => 'float',
        'volume_value' => 'float',
    ];

//    /** @return BelongsTo<Brand,self> */
//    public function brand(): BelongsTo
//    {
//        return $this->belongsTo(Brand::class, 'shop_brand_id');
//    }

    /** @return BelongsToMany<Category> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product', 'product_id', 'category_id')->withTimestamps();
    }

//    /** @return MorphMany<Comment> */
//    public function comments(): MorphMany
//    {
//        return $this->morphMany(Comment::class, 'commentable');
//    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product-images')->useDisk('public');
    }

    public function addMediaFromRequestSafely($key)
    {
        try {
            return $this->addMediaFromRequest($key)->toMediaCollection('product-images');
        } catch (\Throwable $e) {
            logger()->error('Media upload failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
