<?php

use App\Models\Product;
use App\Support\ProductImage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Moves images of products saved before images were stored as files (base64
// in products.image) to storage/app/public/products. Safe to run again: only
// rows that still hold a data URL are touched. Rows are updated without model
// events, so this does not change updated_at or add audit entries.
Artisan::command('products:move-images', function () {
    $moved = 0;
    $failed = 0;

    Product::query()
        ->select('id', 'image')
        ->where('image', 'like', 'data:%')
        ->chunkById(50, function ($products) use (&$moved, &$failed) {
            foreach ($products as $product) {
                $dataUrl = $product->getRawOriginal('image');
                $path = null;

                try {
                    $path = ProductImage::store($dataUrl);
                    DB::table('products')->where('id', $product->id)->update(['image' => $path]);
                    $moved++;
                } catch (Throwable $exception) {
                    ProductImage::delete($path);
                    $failed++;
                    $this->error("Product #{$product->id}: {$exception->getMessage()}");
                }
            }
        });

    $this->info("Moved {$moved} product image(s) to files" . ($failed ? ", {$failed} failed." : '.'));
})->purpose('Move base64 product images from the database to files');
