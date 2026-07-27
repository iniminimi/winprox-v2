<?php

namespace App\Livewire\Pages;

use App\Support\Billing\BillingCatalogViewData;
use App\Support\Marketing\JsonLd;
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

        $layoutData = [
            'title' => __('pricing.meta_title'),
            'socialTitle' => __('pricing.social.og_title'),
            'socialDescription' => __('pricing.social.og_description'),
        ];

        if ($layout === 'components.layouts.marketing') {
            $layoutData['jsonLdGraphs'] = [
                JsonLd::organization(),
                JsonLd::softwareApplication(),
            ];
        }

        return view('livewire.pages.subscription', [
            ...BillingCatalogViewData::catalog(),
            'publicMode' => true,
            'tenant' => auth()->user()?->tenant,
            'billingStatus' => null,
            'portalBatteryState' => null,
            'canManage' => false,
            'selectedPlan' => null,
            'statusMessage' => null,
            'purgeConfirmKind' => null,
        ])->layout($layout, $layoutData);
    }
}
