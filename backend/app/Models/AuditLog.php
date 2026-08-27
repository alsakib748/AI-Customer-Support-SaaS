<?php
// app/Models/AuditLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'user_name',
        'resource_name',
        'action_label',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'first_name' => 'System',
            'last_name' => '',
            'email' => 'system@example.com',
        ]);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get the user's full name.
     */
    public function getUserNameAttribute(): string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->full_name ?: $this->user->email;
        }

        // Try to load if not loaded
        try {
            $user = User::find($this->user_id);
            if ($user) {
                return $user->full_name ?: $user->email;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return 'System';
    }

    /**
     * Get a human-readable resource name.
     */
    public function getResourceNameAttribute(): string
    {
        if (!$this->resource_type || !$this->resource_id) {
            return 'N/A';
        }

        try {
            $model = $this->getResourceModel();
            if ($model) {
                if (method_exists($model, 'getAttribute')) {
                    return $model->getAttribute('name')
                        ?: $model->getAttribute('title')
                        ?: $model->getAttribute('email')
                        ?: '#' . $this->resource_id;
                }
                return '#' . $this->resource_id;
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return $this->resource_type . ' #' . $this->resource_id;
    }

    /**
     * Get a human-readable action label.
     */
    public function getActionLabelAttribute(): string
    {
        return $this->formatActionLabel($this->action);
    }

    /**
     * Get the resource model instance.
     */
    // protected function getResourceModel()
    // {
    //     $modelMap = [
    //         'user' => User::class,
    //         'tenant' => Tenant::class,
    //         'customer' => Customer::class,
    //         'conversation' => Conversation::class,
    //         'ticket' => Ticket::class,
    //         'message' => Message::class,
    //     ];

    //     if (isset($modelMap[$this->resource_type])) {
    //         $modelClass = $modelMap[$this->resource_type];
    //         if (class_exists($modelClass)) {
    //             try {
    //                 return $modelClass::withTrashed()->find($this->resource_id);
    //             } catch (\Exception $e) {
    //                 return null;
    //             }
    //         }
    //     }

    //     return null;
    // }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeForTenant(Builder $query, $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser(Builder $query, $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAction(Builder $query, $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeResource(Builder $query, $type, $id): Builder
    {
        return $query->where('resource_type', $type)
            ->where('resource_id', $id);
    }

    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeCurrentTenant(Builder $query): Builder
    {
        $tenant = app('current_tenant');
        if ($tenant) {
            return $query->where('tenant_id', $tenant->id);
        }
        return $query;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    protected function formatActionLabel(string $action): string
    {
        $labels = [
            'user_registered' => 'User Registered',
            'user_logged_in' => 'User Logged In',
            'user_logged_out' => 'User Logged Out',
            'user_login_failed' => 'User Login Failed',
            'user_updated' => 'User Updated',
            'user_deleted' => 'User Deleted',
            'user_restored' => 'User Restored',

            'tenant_created' => 'Tenant Created',
            'tenant_updated' => 'Tenant Updated',
            'tenant_switched' => 'Tenant Switched',

            'customer_created' => 'Customer Created',
            'customer_updated' => 'Customer Updated',
            'customer_deleted' => 'Customer Deleted',

            'conversation_created' => 'Conversation Created',
            'conversation_updated' => 'Conversation Updated',
            'conversation_assigned' => 'Conversation Assigned',
            'conversation_resolved' => 'Conversation Resolved',
            'conversation_closed' => 'Conversation Closed',

            'message_sent' => 'Message Sent',
            'message_received' => 'Message Received',

            'ticket_created' => 'Ticket Created',
            'ticket_updated' => 'Ticket Updated',
            'ticket_assigned' => 'Ticket Assigned',
            'ticket_resolved' => 'Ticket Resolved',

            'knowledge_created' => 'Knowledge Created',
            'knowledge_updated' => 'Knowledge Updated',
            'knowledge_deleted' => 'Knowledge Deleted',

            'ai_response_generated' => 'AI Response Generated',
            'ai_escalated' => 'AI Escalated to Human',

            'role_assigned' => 'Role Assigned',
            'role_removed' => 'Role Removed',
            'permission_granted' => 'Permission Granted',
            'permission_revoked' => 'Permission Revoked',
        ];

        return $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    public function getActionColor(): string
    {
        $colors = [
            'user_registered' => 'success',
            'user_logged_in' => 'info',
            'user_logged_out' => 'info',
            'user_login_failed' => 'danger',
            'user_updated' => 'primary',
            'user_deleted' => 'danger',
            'user_restored' => 'warning',

            'tenant_created' => 'success',
            'tenant_updated' => 'primary',
            'tenant_switched' => 'info',

            'customer_created' => 'success',
            'customer_updated' => 'primary',
            'customer_deleted' => 'danger',

            'conversation_created' => 'success',
            'conversation_updated' => 'primary',
            'conversation_assigned' => 'warning',
            'conversation_resolved' => 'success',
            'conversation_closed' => 'secondary',

            'message_sent' => 'info',
            'message_received' => 'info',

            'ticket_created' => 'success',
            'ticket_updated' => 'primary',
            'ticket_assigned' => 'warning',
            'ticket_resolved' => 'success',

            'knowledge_created' => 'success',
            'knowledge_updated' => 'primary',
            'knowledge_deleted' => 'danger',

            'ai_response_generated' => 'info',
            'ai_escalated' => 'warning',

            'role_assigned' => 'primary',
            'role_removed' => 'danger',
            'permission_granted' => 'primary',
            'permission_revoked' => 'danger',
        ];

        return $colors[$this->action] ?? 'secondary';
    }

    public function getActionIcon(): string
    {
        $icons = [
            'user_registered' => 'user-plus',
            'user_logged_in' => 'log-in',
            'user_logged_out' => 'log-out',
            'user_login_failed' => 'alert-circle',
            'user_updated' => 'user-edit',
            'user_deleted' => 'user-x',

            'tenant_created' => 'building',
            'tenant_updated' => 'building-edit',
            'tenant_switched' => 'building-switch',

            'customer_created' => 'user-plus',
            'customer_updated' => 'user-edit',
            'customer_deleted' => 'user-x',

            'conversation_created' => 'message-circle',
            'conversation_updated' => 'message-circle-edit',
            'conversation_assigned' => 'user-check',
            'conversation_resolved' => 'check-circle',
            'conversation_closed' => 'x-circle',

            'message_sent' => 'send',
            'message_received' => 'inbox',

            'ticket_created' => 'ticket',
            'ticket_updated' => 'ticket-edit',
            'ticket_assigned' => 'user-check',
            'ticket_resolved' => 'check-circle',

            'knowledge_created' => 'file-plus',
            'knowledge_updated' => 'file-edit',
            'knowledge_deleted' => 'file-x',

            'ai_response_generated' => 'bot',
            'ai_escalated' => 'user-plus',
        ];

        return $icons[$this->action] ?? 'circle';
    }
}
