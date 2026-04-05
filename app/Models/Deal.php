<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use HasFactory;

    protected $table = 'deals';

    protected $fillable = [
        'lead_id',
        'timestamp',
        'charged_date',
        'was_vd',
        'fronter',
        'closer',
        'fee',
        'owner_name',
        'mailing_address',
        'city_state_zip',
        'primary_phone',
        'secondary_phone',
        'email',
        'weeks',
        'asking_rental',
        'resort_name',
        'resort_city_state',
        'exchange_group',
        'bed_bath',
        'usage',
        'asking_sale_price',
        'name_on_card',
        'card_type',
        'bank',
        'card_number',
        'card_last4',
        'card_brand',
        'exp_date',
        'billing_address',
        'bank2',
        'card_number2',
        'card_last4_2',
        'card_brand2',
        'exp_date2',
        'using_timeshare',
        'looking_to_get_out',
        'verification_num',
        'notes',
        'login_info',
        'correspondence',
        'files',
        'snr',
        'login',
        'merchant',
        'app_login',
        'assigned_admin',
        'status',
        'charged',
        'charged_back',
        'closing_date',
        'disposition_status',
        'callback_date',
        'last_edited_by',
        'last_edited_at',
        'updated_by',
        'is_locked',
        'is_vd_deal',
        'fronter_role',
        'closer_comm_pct',
        'closer_comm_amount',
        'fronter_comm_amount',
        'snr_deduction',
        'vd_deduction',
        'closer_net_pay',
        'payroll_week',
        'payroll_finalized',
        // Pipeline tracking fields
        'closer_user_id',
        'verification_admin_user_id',
        'charge_status',
        'verification_status',
        'sent_to_verification_by_user_id',
        'sent_to_verification_at',
        'verification_received_at',
        'charged_by_user_id',
        'charged_at',
        'is_green',
    ];

    /**
     * CVV fields (cv2, cv2_2) are intentionally excluded from $fillable.
     * They have been permanently destroyed in the migration and must
     * NEVER be stored, displayed, or accepted as input.
     */

    protected $casts = [
        'closing_date' => 'date',
        'callback_date' => 'datetime',
        'last_edited_at' => 'datetime',
        'is_locked' => 'boolean',
        'is_vd_deal' => 'boolean',
        'payroll_finalized' => 'boolean',
        'closer_comm_pct' => 'decimal:2',
        'closer_comm_amount' => 'decimal:2',
        'fronter_comm_amount' => 'decimal:2',
        'snr_deduction' => 'decimal:2',
        'vd_deduction' => 'decimal:2',
        'closer_net_pay' => 'decimal:2',
        'timestamp' => 'date',
        'charged_date' => 'date',
        'correspondence' => 'array',
        'files' => 'array',
        'fee' => 'decimal:2',
        'sent_to_verification_at' => 'datetime',
        'verification_received_at' => 'datetime',
        'charged_at' => 'datetime',
        'is_green' => 'boolean',
        'card_number' => SafeEncrypted::class,
        'card_number2' => SafeEncrypted::class,
    ];

    /**
     * Fields hidden from array/JSON serialization by default.
     * Sensitive card data should never leak into Livewire payloads or API responses.
     */
    protected $hidden = [
        'card_number',
        'card_number2',
        'cv2',
        'cv2_2',
    ];

    // ── Field groupings for permission-based access ─────────────

    /** Fields any authorized user can view/edit */
    public const CLIENT_INFO_FIELDS = [
        'owner_name', 'mailing_address', 'city_state_zip', 'primary_phone',
        'secondary_phone', 'email', 'notes', 'status', 'assigned_admin',
    ];

    /** Deal sheet / timeshare detail fields */
    public const DEAL_SHEET_FIELDS = [
        'fee', 'weeks', 'asking_rental', 'resort_name', 'resort_city_state',
        'exchange_group', 'bed_bath', 'usage', 'asking_sale_price',
        'using_timeshare', 'looking_to_get_out', 'verification_num',
        'fronter', 'closer', 'was_vd', 'snr', 'merchant',
    ];

    /** Banking fields */
    public const BANKING_FIELDS = [
        'bank', 'bank2', 'billing_address',
    ];

    /** Sensitive financial / payment fields (require elevated permission) */
    public const PAYMENT_FIELDS = [
        'name_on_card', 'card_type', 'card_last4', 'card_brand',
        'exp_date', 'card_last4_2', 'card_brand2', 'exp_date2',
    ];

    /** Fields that are editable for payment profile (safe subset) */
    public const EDITABLE_PAYMENT_FIELDS = [
        'name_on_card', 'card_type', 'card_brand', 'exp_date',
        'card_brand2', 'exp_date2', 'billing_address',
    ];

    // ── Masking helpers ─────────────────────────────────────────

    /**
     * Get masked display of primary card: e.g. "Visa ****1234"
     */
    public function getMaskedCardAttribute(): string
    {
        if (!$this->card_last4) return '--';
        $brand = $this->card_brand ?: $this->card_type ?: 'Card';
        return "{$brand} ****{$this->card_last4}";
    }

    /**
     * Get masked display of secondary card
     */
    public function getMaskedCard2Attribute(): string
    {
        if (!$this->card_last4_2) return '--';
        $brand = $this->card_brand2 ?: 'Card';
        return "{$brand} ****{$this->card_last4_2}";
    }

    // ── Relationships ───────────────────────────────────────────

    public function fronterUser()
    {
        return $this->belongsTo(User::class, 'fronter');
    }

    public function closerUser()
    {
        return $this->belongsTo(User::class, 'closer');
    }

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'assigned_admin');
    }

    public function auditLogs()
    {
        return $this->hasMany(ClientAuditLog::class, 'deal_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function closerUserRelation()
    {
        return $this->belongsTo(User::class, 'closer_user_id');
    }

    public function verificationAdmin()
    {
        return $this->belongsTo(User::class, 'verification_admin_user_id');
    }

    public function chargedBy()
    {
        return $this->belongsTo(User::class, 'charged_by_user_id');
    }

    public function sentToVerificationBy()
    {
        return $this->belongsTo(User::class, 'sent_to_verification_by_user_id');
    }

    public function pipelineEvents()
    {
        return $this->hasMany(PipelineEvent::class);
    }

    public function crmNotes()
    {
        return $this->morphMany(CrmNote::class, 'noteable');
    }

    // ── Pipeline scopes ─────────────────────────────────────

    public function scopeCharged($query)
    {
        return $query->where('charged', 'yes');
    }

    public function scopeGreen($query)
    {
        return $query->where('is_green', true);
    }

    public function scopeNotCharged($query)
    {
        return $query->where('charge_status', 'not_charged');
    }
}
