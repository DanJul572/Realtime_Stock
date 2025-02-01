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
        ->join('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
        ->select(
            'transactions.id as transaction_id', 
            'transactions.count as transaction_count',
            'transactions.created_at as transaction_created_at',
            'users.name as user_name',
            'products.name as product_name',
            'products.type as product_type',
            'transaction_types.name as transaction_type_name',
        )
        ->where('transactions.id', 'like', '%' . $quickFilter . '%')
        ->orWhere('transactions.count', 'like', '%' . $quickFilter . '%')
        ->orWhere('users.name', 'like', '%' . $quickFilter . '%')
        ->orWhere('products.name', 'like', '%' . $quickFilter . '%')
        ->orWhere('products.type', 'like', '%' . $quickFilter . '%')
        ->orWhere('transaction_types.name', 'like', '%' . $quickFilter . '%')
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
            'count' => $request->input('count'),
            'product_id' => $request->input('product_id'),
            'transaction_type_id' => $request->input('transaction_type_id'),
            'user_id' => $userId,
        ]);

        if ($transaction->transactionType->id == 1) {
            $transaction->product()->increment('stock', $request->input('count'));
        } else {
            $transaction->product()->decrement('stock', $request->input('count'));
        }

        return $transaction;
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->transactionType->id == 1) {
            $transaction->product()->decrement('stock', $transaction->count);
        } else {
            $transaction->product()->increment('stock', $transaction->count);
        }
        $transaction->delete();
        return 'Transaction has been deleted.';
    }
}
