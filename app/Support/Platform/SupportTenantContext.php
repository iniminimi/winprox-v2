<?php

namespace App\Support\Platform;

/**
 * Sessie-gebaseerde tenant-context voor platform-superusers (support view).
 */
final class SupportTenantContext
{
    public const SESSION_KEY = 'support_tenant_id';

    public static function isActive(): bool
    {
        return self::activeTenantId() !== null;
    }

    public static function activeTenantId(): ?int
    {
        $id = session(self::SESSION_KEY);

        if ($id === null || $id === '') {
            return null;
        }

        return (int) $id;
    }

    public static function start(int $tenantId): void
    {
        session([self::SESSION_KEY => $tenantId]);
    }

    public static function stop(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
