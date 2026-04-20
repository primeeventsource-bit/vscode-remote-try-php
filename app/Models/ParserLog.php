<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParserLog extends Model
{
    use HasFactory;

    protected $table = 'parser_logs';

    protected $fillable = [
        'statement_id',
        'level',
        'section',
        'page_number',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function statement()
    {
        return $this->belongsTo(MerchantStatement::class, 'statement_id');
    }
}
