<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeSnapshot extends Model
{
    use HasFactory;

    // Columns from the migration: id, user_id, statement_id, total_fees, by_category, timestamps
    protected $fillable = [
        'user_id',
        'statement_id',
        'total_fees',
        'by_category',   // JSON blob like: {"foreign_tx_fee": 12.5, ...}
    ];

    // Let Eloquent JSON-encode/decode automatically
    protected $casts = [
        'by_category' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function statement()
    {
        return $this->belongsTo(\App\Models\Statement::class);
    }
}
