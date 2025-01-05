<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'colorCode',
        'description',
        'image',
        'name',
        'size',
        'stock',
        'surface',
        'type',
    ];
}
