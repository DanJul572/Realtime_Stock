<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;

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

        return Product::select(
            'products.id',
            'products.name as product_name',
            'products.size',
            'products.type',
            'products.stock',
            'products.price_1',
            'products.price_2',
            'categories.name as category_name')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereLike('products.name', '%' . $quickFilter . '%')
            ->orWhereLike('size', '%' . $quickFilter . '%')
            ->orWhereLike('type', '%' . $quickFilter . '%')
            ->orWhereLike('stock', '%' . $quickFilter . '%')
            ->orWhereLike('price_1', '%' . $quickFilter . '%')
            ->orWhereLike('price_2', '%' . $quickFilter . '%')
            ->orWhereLike('categories.name', '%' . $quickFilter . '%')
            ->orderBy($orderBy, $order)
            ->paginate(10);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        return Product::create($request->validated());
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
        $product->update($request->validated());
        return $product->fresh();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return 'Product has been deleted.';
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
