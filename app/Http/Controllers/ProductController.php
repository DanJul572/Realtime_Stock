<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Support\ProductImage;
use Closure;
use Illuminate\Http\Request;
use Throwable;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $order = $request->input('order');
        $orderBy = $request->input('orderBy');
        $quickFilter = $request->input('quickFilter');
        $isWithoutImage = $request->input('isWithoutImage');

        $query = Product::select(
            'products.id',
            'products.code',
            'products.name as product_name',
            'products.size',
            'products.type',
            'products.stock',
            'products.price_1',
            'products.price_2',
            'categories.name as category_name'
        )
        ->join('categories', 'products.category_id', '=', 'categories.id')
        ->where(function ($q) use ($quickFilter) {
            if ($quickFilter) {
                $q->where('products.name', 'like', "%$quickFilter%")
                    ->orWhere('products.code', 'like', "%$quickFilter%")
                    ->orWhere('size', 'like', "%$quickFilter%")
                    ->orWhere('type', 'like', "%$quickFilter%")
                    ->orWhere('stock', 'like', "%$quickFilter%")
                    ->orWhere('price_1', 'like', "%$quickFilter%")
                    ->orWhere('price_2', 'like', "%$quickFilter%")
                    ->orWhere('categories.name', 'like', "%$quickFilter%");
            }
        });

        if ($isWithoutImage === 'true') {
            $query->whereNull('products.image');
        }

        return $query->orderBy($orderBy, $order)->paginate(10);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        return $this->saveWithImage($request->validated(), fn (array $data) => Product::create($data));
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return $product->load('category');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $oldImage = $product->getRawOriginal('image');
        $this->saveWithImage($request->validated(), fn (array $data) => $product->update($data));

        if ($product->getRawOriginal('image') !== $oldImage) {
            ProductImage::deleteAfterCommit($oldImage);
        }

        return $product->fresh();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        ProductImage::deleteAfterCommit($product->getRawOriginal('image'));
        return 'Product has been deleted.';
    }

    /**
     * Stores a newly uploaded image as a file and saves its path instead of
     * the base64 data. The file is removed again when saving fails, as the
     * database rollback cannot undo it.
     */
    private function saveWithImage(array $data, Closure $save)
    {
        $newImage = null;
        if (!empty($data['image'])) {
            $data['image'] = $newImage = ProductImage::store($data['image']);
        }

        try {
            return $save($data);
        } catch (Throwable $exception) {
            ProductImage::delete($newImage);
            throw $exception;
        }
    }

    /**
     * Find a product by its barcode / QR code (used by the scanner).
     */
    public function findByCode(Request $request)
    {
        $code = $request->input('code');
        if (!is_string($code) || $code === '') {
            return $this->codeRequired();
        }

        $product = Product::with('category')->where('code', $code)->first();

        if (!$product) {
            return response()->json([
                'error' => 'Product not found',
                'statusCode' => 404,
            ], 404);
        }

        return $product;
    }

    /**
     * Check whether a code is still free, optionally ignoring one product
     * (the product being edited). Used before generating/saving a code.
     */
    public function checkCode(Request $request)
    {
        $code = $request->input('code');
        if (!is_string($code) || $code === '') {
            return $this->codeRequired();
        }

        $exists = Product::where('code', $code)
            ->when($request->input('ignoreId'), function ($query, $ignoreId) {
                $query->where('id', '!=', $ignoreId);
            })
            ->exists();

        return [
            'code' => $code,
            'available' => !$exists,
        ];
    }

    private function codeRequired()
    {
        return response()->json([
            'error' => 'The code field is required.',
            'statusCode' => 400,
        ], 400);
    }

    public function options(Request $request)
    {
        $categoryFilter = $request->input('categoryFilter');

        return Product::select('id', 'name', 'type', 'size')
        ->where('category_id', '=', $categoryFilter)
        ->orderBy('name', 'asc')
        ->get()
        ->map(function ($item) {
            return [
                'label' => $item->name . ' - ' . $item->type . ' - ' . $item->size,
                'value' => $item->id,
            ];
        });
    }
}
