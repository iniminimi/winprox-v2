<?php

namespace App\Support\Time;

/**
 * BLE-aanwezigheidstoken van het klokscherm (geen QR-venster).
 *
 * Binary (32 bytes) / hex (64 tekens):
 * id_fp(12) | timestamp(4 BE) | boot_id(4 BE) | counter(4 BE) | hmac(8)
 * id_fp = eerste 12 bytes van SHA-256(display_id) — display_id is opaque
 * (Str::random), géén pure hex.
 * HMAC = eerste 8 bytes van HMAC-SHA256(secret, "display_id|ts|boot|counter")
 */
final class ClockDisplayPresenceToken
{
    public const BINARY_LENGTH = 32;

    public const HEX_LENGTH = 64;

    public const FINGERPRINT_BYTES = 12;

    public const HMAC_BYTES = 8;

    /**
     * @return array{id_fingerprint: string, timestamp: int, boot_id: int, counter: int, hmac: string}|null
     */
    public static function parse(string $hexOrBinary): ?array
    {
        $raw = self::toBinary($hexOrBinary);
        if ($raw === null || strlen($raw) !== self::BINARY_LENGTH) {
            return null;
        }

        $fingerprint = substr($raw, 0, self::FINGERPRINT_BYTES);
        $timestamp = unpack('N', substr($raw, 12, 4))[1];
        $bootId = unpack('N', substr($raw, 16, 4))[1];
        $counter = unpack('N', substr($raw, 20, 4))[1];
        $hmac = bin2hex(substr($raw, 24, self::HMAC_BYTES));

        return [
            'id_fingerprint' => $fingerprint,
            'timestamp' => $timestamp,
            'boot_id' => $bootId,
            'counter' => $counter,
            'hmac' => $hmac,
        ];
    }

    /** Eerste 12 bytes van SHA-256(display_id). */
    public static function fingerprint(string $displayId): string
    {
        return substr(hash('sha256', $displayId, true), 0, self::FINGERPRINT_BYTES);
    }

    public static function message(string $displayId, int $timestamp, int $bootId, int $counter): string
    {
        // Unsigned decimal — zelfde als firmware %lu op uint32_t.
        return $displayId.'|'
            .sprintf('%u', $timestamp)
            .'|'.sprintf('%u', $bootId)
            .'|'.sprintf('%u', $counter);
    }

    public static function hmacHex(string $secret, string $displayId, int $timestamp, int $bootId, int $counter): string
    {
        $full = hash_hmac('sha256', self::message($displayId, $timestamp, $bootId, $counter), $secret);

        return substr($full, 0, self::HMAC_BYTES * 2);
    }

    public static function buildBinary(string $displayId, string $secret, int $timestamp, int $bootId, int $counter): string
    {
        if ($displayId === '' || $secret === '') {
            return '';
        }
        $fp = self::fingerprint($displayId);
        $hmac = @hex2bin(self::hmacHex($secret, $displayId, $timestamp, $bootId, $counter));
        if (! is_string($hmac) || strlen($hmac) !== self::HMAC_BYTES) {
            return '';
        }

        return $fp.pack('N', $timestamp).pack('N', $bootId).pack('N', $counter).$hmac;
    }

    public static function replayKey(string $displayId, int $bootId, int $counter): string
    {
        return 'clock_presence:'.$displayId.':'.$bootId.':'.$counter;
    }

    private static function toBinary(string $hexOrBinary): ?string
    {
        if (strlen($hexOrBinary) === self::BINARY_LENGTH) {
            return $hexOrBinary;
        }
        $hex = strtolower(trim($hexOrBinary));
        if (strlen($hex) !== self::HEX_LENGTH || ! ctype_xdigit($hex)) {
            return null;
        }
        $bin = @hex2bin($hex);

        return is_string($bin) ? $bin : null;
    }
}
