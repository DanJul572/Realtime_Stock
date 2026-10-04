<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'product_id', 'count', 'transaction_type_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function auditType(): string
    {
        return 'transaction';
    }

    // "#12 · Marmer Hitam"
    public function auditLabel(): ?string
    {
        return trim('#' . $this->getKey() . ' · ' . $this->product?->name, ' ·');
    }
}
