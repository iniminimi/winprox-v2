<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Communication\FillTenantContentTranslationsAction;
use Illuminate\Console\Command;

class FillTenantContentTranslationsCommand extends Command
{
    protected $signature = 'winprox:fill-tenant-translations
                            {tenant=1 : Tenant-id (demo handleiding = 1)}
                            {--progress : Toon voortgang per item}';

    protected $description = 'Vul content-vertalingen voor één tenant (slots + Ollama + import, incl. locaties)';

    public function handle(FillTenantContentTranslationsAction $fill): int
    {
        $tenantId = (int) $this->argument('tenant');

        if ($tenantId <= 0) {
            $this->error('Ongeldige tenant-id.');

            return self::FAILURE;
        }

        $this->info("Vullen content-vertalingen voor tenant #{$tenantId}…");
        $this->line('Ollama: '.(config('ollama.enabled') ? config('ollama.url').' / '.config('ollama.model') : 'uit'));

        $onProgress = null;
        if ((bool) $this->option('progress')) {
            $onProgress = function (int $done, int $total, array $meta): void {
                $bits = [];
                foreach (['issue_id', 'task_id', 'unit_id', 'location_id', 'announcement_id', 'document_id', 'esg_indicator_id', 'locale'] as $key) {
                    if (! empty($meta[$key])) {
                        $bits[] = $key.'='.$meta[$key];
                    }
                }
                $this->line("  {$done}/{$total} ".implode(' ', $bits));
            };
        }

        $result = $fill->handle($tenantId, null, $onProgress);

        $this->newLine();
        $this->info("Bron-taal genormaliseerd: {$result['normalized']} record(s)");
        $this->info("Pending vertaald: {$result['pending']}");
        $this->info("Geïmporteerd: {$result['imported']}");

        return self::SUCCESS;
    }
}
