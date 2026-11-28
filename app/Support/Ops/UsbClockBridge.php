<?php

declare(strict_types=1);

namespace App\Support\Ops;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * USB-klokscherm (ESP32-S3 via CH340). De poort wordt niet met een korte
 * serial-open aangeraakt: alleen list_ports, daarna esptool.
 */
class UsbClockBridge
{
    public function available(): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        foreach (['python', 'esptool', 'bootloader', 'partitions', 'firmware'] as $key) {
            $path = (string) config('ops.usb_flash.'.$key);

            if ($path === '' || ! is_file($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function clockPorts(): array
    {
        if (! $this->available()) {
            return [];
        }

        $ran = $this->run([
            $this->path('python'),
            '-c',
            "from serial.tools import list_ports\n"
            ."for p in list_ports.comports():\n"
            ."    print(f'{p.device}\\t{p.vid or 0}\\t{p.pid or 0}')\n",
        ], 20);

        if (! $ran['ok']) {
            return [];
        }

        $vid = (int) config('ops.usb_flash.vid');
        $pid = (int) config('ops.usb_flash.pid');
        $ports = [];
        foreach (preg_split('/\R/', $ran['output']) ?: [] as $line) {
            $parts = explode("\t", trim($line));
            if (count($parts) !== 3) {
                continue;
            }
            if ((int) $parts[1] === $vid && (int) $parts[2] === $pid && $this->isComPort($parts[0])) {
                $ports[] = $parts[0];
            }
        }

        return $ports;
    }

    public function readMac(string $port): ?string
    {
        if (! $this->isComPort($port)) {
            return null;
        }

        $ran = $this->run([
            $this->path('python'),
            $this->path('esptool'),
            '--chip', 'esp32s3',
            '--port', $port,
            '--baud', '115200',
            '--before', 'default_reset',
            '--after', 'hard_reset',
            '--connect-attempts', '5',
            '--no-stub',
            'read_mac',
        ], 45);

        if (! preg_match('/MAC:\s*([0-9A-Fa-f:]{17})/', $ran['output'], $match)) {
            return null;
        }

        $mac = HardwareMac::normalize($match[1]);

        return HardwareMac::isValid($mac) ? $mac : null;
    }

    public function flash(string $port): bool
    {
        if (! $this->available() || ! $this->isComPort($port)) {
            return false;
        }

        set_time_limit(320);

        $ran = $this->run([
            $this->path('python'),
            $this->path('esptool'),
            '--chip', 'esp32s3',
            '--port', $port,
            '--baud', '115200',
            '--before', 'default_reset',
            '--after', 'hard_reset',
            '--connect-attempts', '5',
            '--no-stub',
            'write_flash', '-z',
            '0x0000', $this->path('bootloader'),
            '0x8000', $this->path('partitions'),
            '0x10000', $this->path('firmware'),
        ], 300);

        return $ran['ok'] && str_contains($ran['output'], 'Hash of data verified');
    }

    /**
     * @param  list<string>  $command
     * @return array{ok: bool, output: string}
     */
    private function run(array $command, int $timeout): array
    {
        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return ['ok' => false, 'output' => ''];
        }

        return [
            'ok' => $process->isSuccessful(),
            'output' => $process->getOutput()."\n".$process->getErrorOutput(),
        ];
    }

    private function path(string $key): string
    {
        return (string) config('ops.usb_flash.'.$key);
    }

    private function isComPort(string $port): bool
    {
        return (bool) preg_match('/^COM\d+$/', $port);
    }
}
