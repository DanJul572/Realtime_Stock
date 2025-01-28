<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $order = $request->input('order');
        $orderBy = $request->input('orderBy');
        $quickFilter = $request->input('quickFilter');

        return Transaction::join('users', 'transactions.user_id', '=', 'users.id')
        ->join('products', 'transactions.product_id', '=', 'products.id')
        ->select(
            'transactions.id as transaction_id', 
            'transactions.count as transaction_count',
            'users.name as user_name',
            'products.name as product_name',
            'products.type as product_type',
        )
        ->where('transactions.id', 'like', '%' . $quickFilter . '%')
        ->orWhere('transactions.count', 'like', '%' . $quickFilter . '%')
        ->orWhere('users.name', 'like', '%' . $quickFilter . '%')
        ->orWhere('products.name', 'like', '%' . $quickFilter . '%')
        ->orWhere('products.type', 'like', '%' . $quickFilter . '%')
        ->orderBy($orderBy, $order)
        ->paginate(10);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        $userId = auth()->user()->id;

        $transaction = Transaction::create([
            'user_id' => $userId,
            'product_id' => $request->input('product_id'),
            'count' => $request->input('count'),
        ]);

        $transaction->product()->decrement('stock', $request->input('count'));

        return $transaction;
    }
}
