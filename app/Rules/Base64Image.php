<?php

namespace App\Rules;

use App\Support\ProductImage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Base64Image implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !ProductImage::decode($value)) {
            $fail('The :attribute must be a JPEG, PNG, WEBP or GIF image of at most '
                . (ProductImage::MAX_BYTES / 1024 / 1024) . ' MB.');
        }
    }
}
