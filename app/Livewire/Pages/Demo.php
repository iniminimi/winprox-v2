<?php

namespace App\Livewire\Pages;

use App\Actions\Marketing\SubmitDemoRequestAction;
use App\Http\Requests\Marketing\StoreDemoRequestRequest;
use App\Support\Marketing\JsonLd;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class Demo extends Component
{
    public string $company = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $municipality = '';

    public string $preference = 'onsite';

    public string $note = '';

    public bool $submitted = false;

    public function submit(SubmitDemoRequestAction $action): void
    {
        if ($this->submitted) {
            return;
        }

        $request = new StoreDemoRequestRequest;
        $validated = $this->validate($request->rules(), $request->messages());

        $validated['email'] = mb_strtolower(trim($validated['email']));

        $action->handle($validated, (string) request()->ip(), app()->getLocale());

        $this->submitted = true;
    }

    public function render()
    {
        $layout = auth()->check()
            ? 'components.layouts.app'
            : 'components.layouts.marketing';

        $layoutData = [
            'title' => __('demo.meta_title'),
            'socialTitle' => __('demo.social.og_title'),
            'socialDescription' => __('demo.social.og_description'),
        ];

        if ($layout === 'components.layouts.marketing') {
            $layoutData['jsonLdGraphs'] = [
                JsonLd::organization(),
            ];
        }

        return view('livewire.pages.demo')
            ->layout($layout, $layoutData);
    }
}
