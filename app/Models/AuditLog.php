<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const TYPES = ['product', 'category', 'user', 'transaction', 'login'];

    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'label',
        'event',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'new_values' => 'array',
            'old_values' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
