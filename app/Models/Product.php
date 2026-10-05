<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function auditType(): string
    {
        return 'product';
    }
}
