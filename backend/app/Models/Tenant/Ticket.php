<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    protected $table = 'tickets';

    protected $connection = 'tenant';

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'conversation_id',
        'subject',
        'description',
        'status',
        'priority',
        'type',
        'source',
        'assigned_user_id',
        'created_by_user_id',
        'due_at',
        'resolved_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'priority_label',
        'priority_color',
        'type_label',
        'source_label',
        'is_open',
        'is_in_progress',
        'is_pending',
        'is_resolved',
        'is_closed',
        'is_overdue',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'pending' => 'Pending',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'open' => 'info',
            'in_progress' => 'warning',
            'pending' => 'secondary',
            'resolved' => 'success',
            'closed' => 'danger',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function getPriorityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
        return $labels[$this->priority] ?? ucfirst($this->priority);
    }

    public function getPriorityColorAttribute(): string
    {
        $colors = [
            'low' => 'secondary',
            'normal' => 'info',
            'high' => 'warning',
            'urgent' => 'danger',
        ];
        return $colors[$this->priority] ?? 'secondary';
    }

    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'general' => 'General',
            'technical' => 'Technical',
            'billing' => 'Billing',
            'account' => 'Account',
            'bug' => 'Bug',
            'feature_request' => 'Feature Request',
            'other' => 'Other',
        ];
        return $labels[$this->type] ?? ucfirst($this->type);
    }

    public function getSourceLabelAttribute(): string
    {
        $labels = [
            'manual' => 'Manual',
            'conversation' => 'Conversation',
            'widget' => 'Chat Widget',
            'email' => 'Email',
            'api' => 'API',
            'ai' => 'AI Assistant',
        ];
        return $labels[$this->source] ?? ucfirst($this->source);
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->status === 'open';
    }

    public function getIsInProgressAttribute(): bool
    {
        return $this->status === 'in_progress';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsResolvedAttribute(): bool
    {
        return $this->status === 'resolved';
    }

    public function getIsClosedAttribute(): bool
    {
        return $this->status === 'closed';
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_at || in_array($this->status, ['resolved', 'closed'])) {
            return false;
        }
        return $this->due_at->isPast();
    }

    public function getAssignedUserNameAttribute(): ?string
    {
        if (!$this->assigned_user_id) {
            return null;
        }
        // Will be resolved from central DB
        return null;
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'in_progress', 'pending']);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_user_id');
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', ['resolved', 'closed']);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'ILIKE', "%{$search}%")
                ->orWhere('subject', 'ILIKE', "%{$search}%")
                ->orWhere('description', 'ILIKE', "%{$search}%")
                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('phone', 'ILIKE', "%{$search}%");
                });
        });
    }

    // ============================================
    // METHODS
    // ============================================

    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            'open' => ['in_progress', 'pending', 'resolved'],
            'in_progress' => ['pending', 'resolved'],
            'pending' => ['in_progress', 'resolved'],
            'resolved' => ['closed', 'open'],
            'closed' => ['open'],
        ];

        return in_array($newStatus, $transitions[$this->status] ?? []);
    }

    public function canAssign(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'pending']);
    }

    public function canResolve(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'pending']);
    }

    public function canReopen(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function canClose(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'pending', 'resolved']);
    }

    public function getStatusActions(): array
    {
        return [
            'can_assign' => $this->canAssign(),
            'can_resolve' => $this->canResolve(),
            'can_reopen' => $this->canReopen(),
            'can_close' => $this->canClose(),
        ];
    }

    public function assign(int $userId): void
    {
        $this->update(['assigned_user_id' => $userId]);
    }

    public function unassign(): void
    {
        $this->update(['assigned_user_id' => null]);
    }

    public function start(): void
    {
        $this->update(['status' => 'in_progress']);
    }

    public function setPending(): void
    {
        $this->update(['status' => 'pending']);
    }

    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }
}
