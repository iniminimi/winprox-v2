<?php

namespace App\Support\Translation;

use App\Contracts\TranslationSyncRemoteClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class TranslationSyncRemoteGateway implements TranslationSyncRemoteClient
{
    public function assertConfigured(): void
    {
        if (! config('translation_sync.enabled', false)) {
            throw new InvalidArgumentException('translation_sync_not_enabled');
        }

        foreach (['ssh_host', 'ssh_user', 'remote_path'] as $key) {
            if ((string) config("translation_sync.{$key}") === '') {
                throw new InvalidArgumentException('translation_sync_not_configured');
            }
        }
    }

    public function runExportOnRemote(): void
    {
        $this->runRemoteArtisan('translation:export');
    }

    public function downloadExport(string $localPath): void
    {
        File::ensureDirectoryExists(dirname($localPath));

        $this->runScp(
            $this->remoteStoragePath('exports', (string) config('translation_sync.export_filename')),
            $localPath,
            download: true,
        );
    }

    public function uploadImport(string $localPath): void
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('translation_sync_import_file_missing');
        }

        $this->runScp(
            $localPath,
            $this->remoteStoragePath('imports', (string) config('translation_sync.import_filename')),
            download: false,
        );
    }

    public function runImportOnRemote(): void
    {
        $this->runRemoteArtisan('translation:import');
    }

    private function runRemoteArtisan(string $command): void
    {
        $remotePath = escapeshellarg((string) config('translation_sync.remote_path'));
        $remoteCommand = "cd {$remotePath} && php artisan {$command}";

        $result = Process::timeout((int) config('translation_sync.timeout_seconds', 7200))
            ->run($this->sshCommand($remoteCommand));

        if (! $result->successful()) {
            throw new RuntimeException('translation_sync_remote_command_failed:'.trim($result->errorOutput() ?: $result->output()));
        }
    }

    private function runScp(string $from, string $to, bool $download): void
    {
        $source = $download ? $this->sshTarget().':'.$from : $from;
        $target = $download ? $to : $this->sshTarget().':'.$to;

        $result = Process::timeout((int) config('translation_sync.timeout_seconds', 7200))
            ->run($this->scpCommand([$source, $target]));

        if (! $result->successful()) {
            throw new RuntimeException('translation_sync_scp_failed:'.trim($result->errorOutput() ?: $result->output()));
        }
    }

    /**
     * @return list<string>
     */
    private function sshCommand(string $remoteCommand): array
    {
        return array_merge(['ssh'], $this->sshOptions(), [$this->sshTarget(), $remoteCommand]);
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function scpCommand(array $paths): array
    {
        return array_merge(['scp'], $this->scpOptions(), $paths);
    }

    /**
     * @return list<string>
     */
    private function sshOptions(): array
    {
        $options = $this->sharedTransportOptions();

        $port = (int) config('translation_sync.ssh_port', 22);
        if ($port !== 22) {
            $options[] = '-p';
            $options[] = (string) $port;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function scpOptions(): array
    {
        $options = $this->sharedTransportOptions();

        $port = (int) config('translation_sync.ssh_port', 22);
        if ($port !== 22) {
            $options[] = '-P';
            $options[] = (string) $port;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function sharedTransportOptions(): array
    {
        $options = [
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
        ];

        $key = config('translation_sync.ssh_key');
        if (is_string($key) && $key !== '') {
            $options[] = '-i';
            $options[] = $key;
        }

        return $options;
    }

    private function sshTarget(): string
    {
        return (string) config('translation_sync.ssh_user').'@'.(string) config('translation_sync.ssh_host');
    }

    private function remoteStoragePath(string $folder, string $filename): string
    {
        $base = rtrim((string) config('translation_sync.remote_path'), '/');

        return $base.'/storage/app/'.$folder.'/'.$filename;
    }
}
