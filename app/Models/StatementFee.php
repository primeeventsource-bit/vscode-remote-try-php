<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatementFee extends Model
{
    use HasFactory;

    protected $table = 'statement_fees';

    protected $fillable = [
        'statement_id',
        'fee_description',
        'fee_category',
        'quantity',
        'basis_amount',
        'rate',
        'fee_total',
        'raw_row_text',
    ];

    protected $casts = [
        'basis_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'fee_total' => 'decimal:2',
    ];

    public const CATEGORIES = [
        'discount', 'dispute', 'network', 'auth',
        'monthly', 'compliance', 'reserve', 'misc',
    ];

    /**
     * Fee description to category auto-mapping.
     */
    public static function categorizeDescription(string $description): string
    {
        $desc = strtolower($description);

        if (preg_match('/discount|qual|non.?qual|mid.?qual|interchange/i', $desc)) return 'discount';
        if (preg_match('/dispute|chargeback|retrieval|pre.?arb|arbitration|representment/i', $desc)) return 'dispute';
        if (preg_match('/assessment|brand|network|dues|access|nabu|kilobyte/i', $desc)) return 'network';
        if (preg_match('/auth|avs|cvv|gateway|batch|terminal/i', $desc)) return 'auth';
        if (preg_match('/monthly|statement|annual|pci|regulatory|account/i', $desc)) return 'monthly';
        if (preg_match('/compliance|irs|tin|1099/i', $desc)) return 'compliance';
        if (preg_match('/reserve|hold|funding/i', $desc)) return 'reserve';

        return 'misc';
    }

    public function statement()
    {
        return $this->belongsTo(MerchantStatement::class, 'statement_id');
    }
}
