<?php

namespace App\Models;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected $connection = 'central';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'subscription_id',
        'invoice_number',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'currency',
        'status',
        'due_at',
        'paid_at',
        'payment_method',
        'payment_provider',
        'transaction_id',
        'period_starts_at',
        'period_ends_at',
        'stripe_invoice_id',
        'paypal_invoice_id',
        'invoice_pdf_url',
        'hosted_invoice_url',
        'line_items',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'period_starts_at' => 'datetime',
        'period_ends_at' => 'datetime',
        'line_items' => 'array',
        'metadata' => 'array',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'is_paid',
        'is_overdue',
        'formatted_total',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'open' => 'Open',
            'paid' => 'Paid',
            'void' => 'Void',
            'uncollectible' => 'Uncollectible',
            'refunded' => 'Refunded',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'draft' => 'secondary',
            'open' => 'info',
            'paid' => 'success',
            'void' => 'secondary',
            'uncollectible' => 'danger',
            'refunded' => 'warning',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'open'
            && $this->due_at
            && $this->due_at->isPast();
    }

    public function getFormattedTotalAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->total, 2);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'open')
            ->where('due_at', '<', now());
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ============================================
    // METHODS
    // ============================================

    public function markAsPaid(string $paymentMethod = null, string $transactionId = null): self
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
        ]);

        return $this;
    }

    public function markAsVoid(): self
    {
        $this->update(['status' => 'void']);
        return $this;
    }

    public function markAsUncollectible(): self
    {
        $this->update(['status' => 'uncollectible']);
        return $this;
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-';
        $period = date('Ym');

        // Include soft-deleted rows so a deleted number is never reused
        // (invoice_number is unique).
        $max = static::withTrashed()
            ->where('invoice_number', 'LIKE', "{$prefix}{$period}%")
            ->get(['invoice_number'])
            ->reduce(function (?int $carry, $invoice) {
                if (preg_match('/-(\d{6})$/', (string) $invoice->invoice_number, $m)) {
                    return max($carry ?? 0, (int) $m[1]);
                }
                return $carry;
            });

        $newNumber = str_pad(($max ?? 0) + 1, 6, '0', STR_PAD_LEFT);

        return $prefix . $period . '-' . $newNumber;
    }
}
