<?php

namespace App\Notifications\Enums;

/**
 * High-level grouping of a platform notification. Categories let the inbox
 * filter and let preferences mute optional groups without touching producers.
 */
enum NotificationCategory: string
{
    case SystemHealth   = 'system_health';
    case Billing        = 'billing';
    case Tenant         = 'tenant';
    case Security       = 'security';
    case AiIntegrations = 'ai_integrations';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::SystemHealth   => 'System Health',
            self::Billing        => 'Billing',
            self::Tenant         => 'Tenants',
            self::Security       => 'Security',
            self::AiIntegrations => 'AI & Integrations',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SystemHealth   => 'pi pi-heart-pulse',
            self::Billing        => 'pi pi-credit-card',
            self::Tenant         => 'pi pi-building',
            self::Security       => 'pi pi-shield',
            self::AiIntegrations => 'pi pi-sparkles',
        };
    }

    /**
     * Best-effort mapping for notifications persisted by older modules that
     * carry a free-form "type" instead of a category. Keeps legacy records
     * rendering safely in the new inbox.
     */
    public static function fromLegacyType(?string $type): self
    {
        $type = strtolower((string) $type);

        return match (true) {
            str_contains($type, 'payment'),
            str_contains($type, 'subscription'),
            str_contains($type, 'invoice'),
            str_contains($type, 'trial'),
            str_contains($type, 'renewal'),
            str_contains($type, 'billing') => self::Billing,

            str_contains($type, 'tenant'),
            str_contains($type, 'workspace'),
            str_contains($type, 'provision') => self::Tenant,

            str_contains($type, 'security'),
            str_contains($type, 'audit'),
            str_contains($type, 'auth') => self::Security,

            str_contains($type, 'ai'),
            str_contains($type, 'integration'),
            str_contains($type, 'provider') => self::AiIntegrations,

            default => self::SystemHealth,
        };
    }
}
