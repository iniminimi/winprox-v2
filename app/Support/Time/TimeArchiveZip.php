<?php

namespace App\Support\Time;

use App\Models\TenantTimeArchive;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Schrijft en leest de privé-zip. Geen beslissing over wat gewist wordt.
 */
final class TimeArchiveZip
{
    public static function read(string $path, string $name): ?string
    {
        $absolute = self::absolute($path);
        if (! is_file($absolute)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($absolute) !== true) {
            throw new RuntimeException('archive_unreadable');
        }

        $contents = $zip->getFromName($name);
        $zip->close();

        return $contents === false ? null : $contents;
    }

    public static function uncompressedSize(string $path, string $name): ?int
    {
        $absolute = self::absolute($path);
        if (! is_file($absolute)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($absolute) !== true) {
            throw new RuntimeException('archive_unreadable');
        }

        $stat = $zip->statName($name);
        $zip->close();

        return is_array($stat) ? (int) $stat['size'] : null;
    }

    /**
     * @param  array<string, string>  $entries
     */
    public static function add(string $path, array $entries): void
    {
        $target = self::absolute($path);
        $directory = dirname($target);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('archive_unreadable');
        }

        $tmp = $target.'.tmp';
        $zip = new ZipArchive;
        if (is_file($target)) {
            if (! copy($target, $tmp)) {
                throw new RuntimeException('archive_unreadable');
            }
            if ($zip->open($tmp) !== true) {
                @unlink($tmp);
                throw new RuntimeException('archive_unreadable');
            }
        } elseif ($zip->open($tmp, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('archive_unreadable');
        }

        foreach ($entries as $name => $contents) {
            if ($zip->locateName($name) !== false) {
                $zip->deleteName($name);
            }
            if ($zip->addFromString($name, $contents) !== true) {
                $zip->close();
                @unlink($tmp);
                throw new RuntimeException('archive_unreadable');
            }
        }

        if ($zip->close() !== true || ! self::opens($tmp)) {
            @unlink($tmp);
            throw new RuntimeException('archive_unreadable');
        }

        $backup = $target.'.bak';
        $hadTarget = is_file($target);
        if ($hadTarget && ! rename($target, $backup)) {
            @unlink($tmp);
            throw new RuntimeException('archive_unreadable');
        }
        if (! rename($tmp, $target)) {
            if ($hadTarget) {
                @rename($backup, $target);
            }
            @unlink($tmp);
            throw new RuntimeException('archive_unreadable');
        }
        if ($hadTarget) {
            @unlink($backup);
        }
    }

    private static function opens(string $absolute): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($absolute) !== true) {
            return false;
        }
        $ok = $zip->numFiles >= 0;
        $zip->close();

        return $ok;
    }

    private static function absolute(string $path): string
    {
        if (! str_starts_with($path, TenantTimeArchive::DIRECTORY.'/')) {
            throw new RuntimeException('archive_unreadable');
        }

        return Storage::disk(TenantTimeArchive::DISK)->path($path);
    }
}
