<?php

namespace App\Support\Time;

/**
 * BLE-aanwezigheidstoken van het klokscherm (geen QR-venster).
 *
 * Binary (32 bytes) / hex (64 tekens):
 * display_id(12) | timestamp(4 BE) | boot_id(4 BE) | counter(4 BE) | hmac(8)
 * HMAC = eerste 8 bytes van HMAC-SHA256(secret, "display_id|ts|boot|counter")
 * waarbij display_id als 24-hex in de message staat.
 */
final class ClockDisplayPresenceToken
{
    public const BINARY_LENGTH = 32;

    public const HEX_LENGTH = 64;

    public const DISPLAY_ID_BYTES = 12;

    public const HMAC_BYTES = 8;

    /**
     * @return array{display_id: string, timestamp: int, boot_id: int, counter: int, hmac: string}|null
     */
    public static function parse(string $hexOrBinary): ?array
    {
        $raw = self::toBinary($hexOrBinary);
        if ($raw === null || strlen($raw) !== self::BINARY_LENGTH) {
            return null;
        }

        $displayId = bin2hex(substr($raw, 0, self::DISPLAY_ID_BYTES));
        $timestamp = unpack('N', substr($raw, 12, 4))[1];
        $bootId = unpack('N', substr($raw, 16, 4))[1];
        $counter = unpack('N', substr($raw, 20, 4))[1];
        $hmac = bin2hex(substr($raw, 24, self::HMAC_BYTES));

        return [
            'display_id' => $displayId,
            'timestamp' => $timestamp,
            'boot_id' => $bootId,
            'counter' => $counter,
            'hmac' => $hmac,
        ];
    }

    public static function message(string $displayIdHex, int $timestamp, int $bootId, int $counter): string
    {
        return strtolower($displayIdHex).'|'.$timestamp.'|'.$bootId.'|'.$counter;
    }

    public static function hmacHex(string $secret, string $displayIdHex, int $timestamp, int $bootId, int $counter): string
    {
        $full = hash_hmac('sha256', self::message($displayIdHex, $timestamp, $bootId, $counter), $secret);

        return substr($full, 0, self::HMAC_BYTES * 2);
    }

    public static function buildBinary(string $displayIdHex, string $secret, int $timestamp, int $bootId, int $counter): string
    {
        $id = @hex2bin(strtolower($displayIdHex));
        if (! is_string($id) || strlen($id) !== self::DISPLAY_ID_BYTES) {
            return '';
        }
        $hmac = @hex2bin(self::hmacHex($secret, strtolower($displayIdHex), $timestamp, $bootId, $counter));
        if (! is_string($hmac) || strlen($hmac) !== self::HMAC_BYTES) {
            return '';
        }

        return $id.pack('N', $timestamp).pack('N', $bootId).pack('N', $counter).$hmac;
    }

    public static function replayKey(string $displayIdHex, int $bootId, int $counter): string
    {
        return 'clock_presence:'.strtolower($displayIdHex).':'.$bootId.':'.$counter;
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
