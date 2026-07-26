<?php

namespace App\Support\Portal;

use Illuminate\Support\Facades\Cookie;

/**
 * Onthoudt gastgegevens voor unit-QR-reserveringen (cookie, 1 jaar).
 */
final class ReservationGuestSession
{
    public const COOKIE = 'wp_reservation_guest';

    /**
     * @return array{first_name: string, last_name: string, email: string}|null
     */
    public static function read(): ?array
    {
        $raw = Cookie::get(self::COOKIE);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $first = trim((string) ($decoded['first_name'] ?? ''));
        $last = trim((string) ($decoded['last_name'] ?? ''));
        $email = strtolower(trim((string) ($decoded['email'] ?? '')));

        if ($first === '' || $last === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
        ];
    }

    public static function remember(string $firstName, string $lastName, string $email): void
    {
        $payload = json_encode([
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'email' => strtolower(trim($email)),
        ], JSON_UNESCAPED_UNICODE);

        Cookie::queue(cookie(
            self::COOKIE,
            is_string($payload) ? $payload : '',
            60 * 24 * 365,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'Lax'
        ));
    }
}
