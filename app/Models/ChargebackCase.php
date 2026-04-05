<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargebackCase extends Model
{
    protected $table = 'chargeback_cases';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';

    public const DOCUMENT_TYPES = [
        'owners_agreement' => "Owner's Listing Agreement",
        'card_authorization' => 'Credit Card Authorization',
        'invoice_copy' => 'Invoice Copy',
        'terms_dispute_waiver' => 'Terms & Dispute Waiver',
        'transaction_receipt' => 'Transaction Receipt',
        'advertisement_screenshot' => 'Advertisement Screenshot',
        'client_login_report' => 'Client Login Report',
        'welcome_email_confirmation' => 'Welcome Email Confirmation',
        'customer_summary_information' => 'Customer Summary Information',
    ];

    protected $fillable = [
        'client_id', 'deal_id', 'case_number', 'card_type', 'card_brand',
        'processor_name', 'reason_code', 'reason_description',
        'transaction_amount', 'disputed_amount', 'transaction_id', 'order_id',
        'response_due_at', 'sale_date', 'service_start_date',
        'customer_ip_address', 'status', 'internal_comments',
        'created_by_user_id', 'updated_by_user_id',
        'outcome_status', 'submitted_at', 'resolved_at', 'recovered_amount',
        'package_completed_at',
    ];

    protected $casts = [
        'transaction_amount' => 'decimal:2',
        'disputed_amount' => 'decimal:2',
        'recovered_amount' => 'decimal:2',
        'response_due_at' => 'date',
        'sale_date' => 'date',
        'service_start_date' => 'date',
        'submitted_at' => 'datetime',
        'resolved_at' => 'datetime',
        'package_completed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Deal::class, 'client_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    public function evidence()
    {
        return $this->hasMany(ChargebackEvidence::class, 'chargeback_case_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get readiness stats: how many of the 9 required docs are uploaded.
     */
    public function getReadinessAttribute(): array
    {
        $uploaded = $this->evidence->pluck('document_type')->unique()->toArray();
        $required = array_keys(self::DOCUMENT_TYPES);
        $missing = array_diff($required, $uploaded);

        $total = count($required);
        $done = $total - count($missing);

        return [
            'total' => $total,
            'uploaded' => $done,
            'missing' => count($missing),
            'missing_types' => $missing,
            'pct' => $total > 0 ? round($done / $total * 100) : 0,
            'ready' => count($missing) === 0,
        ];
    }
}
