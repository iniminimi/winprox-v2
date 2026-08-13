<?php

namespace App\Actions\Manual;

use App\Enums\ManualCaptureRunStatus;
use App\Support\Manual\ManualCaptureStatusStore;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class ExecuteManualScreenshotCaptureAction
{
    public function __construct(private ManualCaptureStatusStore $statusStore) {}

    /**
     * @return array{exit_code: int, output: string}
     */
    public function handle(?int $actorUserId = null): array
    {
        $this->assertConfigured();

        $this->statusStore->write(ManualCaptureRunStatus::Running, $actorUserId, [
            'started_at' => now()->toIso8601String(),
            'message' => null,
        ]);

        $script = (string) config('manual_capture.script_path');
        if (! is_file($script)) {
            $this->fail($actorUserId, 'manual_capture_script_missing');

            throw new RuntimeException('manual_capture_script_missing');
        }

        $result = Process::timeout((int) config('manual_capture.timeout_seconds', 600))
            ->path(base_path())
            ->env($this->processEnvironment())
            ->run([(string) config('manual_capture.node_bin', 'node'), $script]);

        $output = trim($result->output()."\n".$result->errorOutput());

        if (! $result->successful()) {
            $this->fail($actorUserId, 'manual_capture_process_failed', [
                'exit_code' => $result->exitCode(),
                'output' => $output,
            ]);

            throw new RuntimeException('manual_capture_process_failed');
        }

        $this->statusStore->write(ManualCaptureRunStatus::Completed, $actorUserId, [
            'finished_at' => now()->toIso8601String(),
            'exit_code' => $result->exitCode(),
            'output' => $output,
            'message' => null,
        ]);

        return [
            'exit_code' => $result->exitCode() ?? 0,
            'output' => $output,
        ];
    }

    private function assertConfigured(): void
    {
        $email = (string) config('manual_capture.email');
        $password = (string) config('manual_capture.password');

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('manual_capture_not_configured');
        }
    }

    /**
     * @return array<string, string>
     */
    private function processEnvironment(): array
    {
        $capturePkg = base_path('scripts/capture-pkg/node_modules');

        return array_filter([
            'NODE_PATH' => is_dir($capturePkg) ? $capturePkg : '',
            'PLAYWRIGHT_BROWSERS_PATH' => (string) config('manual_capture.playwright_browsers_path'),
            'MANUAL_CAPTURE_BASE_URL' => (string) config('manual_capture.base_url'),
            'MANUAL_CAPTURE_HOST' => (string) config('manual_capture.host_header'),
            'MANUAL_CAPTURE_EMAIL' => (string) config('manual_capture.email'),
            'MANUAL_CAPTURE_PASSWORD' => (string) config('manual_capture.password'),
            'MANUAL_CAPTURE_OUTPUT_DIR' => (string) config('manual_capture.output_dir'),
            'MANUAL_CAPTURE_CONFIG_PATH' => (string) config('manual_capture.config_path'),
            'MANUAL_CAPTURE_LOCATION_ID' => (string) config('manual_capture.location_id'),
            'MANUAL_CAPTURE_ISSUE_ID' => (string) config('manual_capture.issue_id'),
            'MANUAL_CAPTURE_TASK_ID' => (string) config('manual_capture.task_id'),
            'MANUAL_CAPTURE_UNIT_QR_TOKEN' => (string) config('manual_capture.unit_qr_token'),
            'MANUAL_CAPTURE_CLOCK_POINT_TOKEN' => (string) config('manual_capture.clock_point_token'),
            'MANUAL_CAPTURE_WORKER_FIRST_NAME' => (string) config('manual_capture.worker_first_name'),
            'MANUAL_CAPTURE_WORKER_LAST_NAME' => (string) config('manual_capture.worker_last_name'),
            'MANUAL_CAPTURE_WORKER_ICON' => (string) config('manual_capture.worker_icon'),
            'MANUAL_CAPTURE_CHROME_LOW_RESOURCE' => config('manual_capture.chrome_low_resource') ? '1' : '',
        ], fn (string $value) => $value !== '');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function fail(?int $actorUserId, string $message, array $extra = []): void
    {
        $this->statusStore->write(ManualCaptureRunStatus::Failed, $actorUserId, array_merge([
            'finished_at' => now()->toIso8601String(),
            'message' => $message,
        ], $extra));
    }
}
