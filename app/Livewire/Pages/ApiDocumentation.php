<?php

namespace App\Livewire\Pages;

use App\Actions\Api\ParseMarkdownAction;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use ZipArchive;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ApiDocumentation extends Component
{
    public string $current = 'README';

    public function mount(string $file = 'README'): void
    {
        $this->current = $file;
    }

    public function download()
    {
        $docsPath = base_path('docs/api');
        $zipPath = storage_path('app/api-docs.zip');
        
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create zip file');
        }
        
        $files = File::files($docsPath);
        foreach ($files as $file) {
            if ($file->getExtension() === 'md') {
                $zip->addFile($file->getPathname(), $file->getFilename());
            }
        }
        
        $zip->close();
        
        return Response::download($zipPath, 'winprox-api-documentation.zip')->deleteFileAfterSend(true);
    }

    public function render(ParseMarkdownAction $parseMarkdown)
    {
        $docsPath = base_path('docs/api');
        $filePath = $docsPath . '/' . $this->current . '.md';
        
        if (! File::exists($filePath) || ! str_ends_with($filePath, '.md')) {
            abort(404);
        }

        $content = File::get($filePath);
        
        return view('livewire.pages.api-documentation', [
            'content' => $parseMarkdown->handle($content),
            'sidebar' => $this->getSidebar($docsPath),
        ]);
    }

    protected function getSidebar(string $docsPath): array
    {
        return [
            'README' => 'Overview',
            'authentication' => 'Authentication',
            'issues' => 'Issues',
            'tasks' => 'Tasks',
            'locations' => 'Locations',
            'units' => 'Units',
            'teams' => 'Teams',
            'workers' => 'Workers',
            'webhooks' => 'Webhooks',
        ];
    }
}
