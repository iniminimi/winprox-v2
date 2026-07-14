<?php

namespace App\Livewire\Pages;

use App\Support\Billing\BillingCatalogViewData;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('WinProx')]
class Pricing extends Component
{
    public function render()
    {
        $layout = auth()->check()
            ? 'components.layouts.app'
            : 'components.layouts.marketing';

        return view('livewire.pages.subscription', [
            ...BillingCatalogViewData::catalog(),
            'publicMode' => true,
            'tenant' => auth()->user()?->tenant,
            'billingStatus' => null,
            'portalBatteryState' => null,
            'canManage' => false,
            'selectedPlan' => null,
            'statusMessage' => null,
        ])->layout($layout, [
            'title' => __('pricing.meta_title'),
            'socialTitle' => __('pricing.social.og_title'),
            'socialDescription' => __('pricing.social.og_description'),
        ]);
    }
}
