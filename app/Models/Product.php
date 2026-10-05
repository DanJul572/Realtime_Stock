<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\ProductImage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Auditable;

    protected $fillable = [
        'category_id',
        'code',
        'image',
        'name',
        'price_1',
        'price_2',
        'size',
        'stock',
        'surface',
        'type',
    ];

    // Stored as a file path (or a base64 data URL for older products), returned as URL.
    protected function image(): Attribute
    {
        return Attribute::get(fn (?string $value) => ProductImage::url($value));
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function auditType(): string
    {
        return 'product';
    }
}
