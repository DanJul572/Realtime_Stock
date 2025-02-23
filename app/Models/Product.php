<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'image',
        'name',
        'price_1',
        'price_2',
        'size',
        'stock',
        'surface',
        'type',
    ];
}
