<?php

namespace App\Support;

/**
 * Houdt de actieve tenant-context vast voor de global scope.
 *
 * - Normale gebruiker: tenant volgt uit auth()->user()->tenant_id.
 * - Superuser zonder impersonatie: geen tenant -> scope filtert niet (ziet alles).
 * - Superuser die overneemt: actAs($tenantId) -> scope filtert op die tenant.
 */
class Tenancy
{
    protected static ?int $override = null;

    public static function id(): ?int
    {
        if (static::$override !== null) {
            return static::$override;
        }

        return auth()->user()->tenant_id ?? null;
    }

    public static function actAs(?int $tenantId): void
    {
        static::$override = $tenantId;
    }

    public static function forget(): void
    {
        static::$override = null;
    }
}
