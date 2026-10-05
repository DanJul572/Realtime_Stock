<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'user_id',
        'method',
        'url',
        'status_code',
        'message',
        'request_body',
        'exception_class',
        'exception_message',
        'exception_location',
        'trace',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'request_body' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
