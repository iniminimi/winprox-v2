<?php

declare(strict_types=1);

namespace App\Support\Marketing;

final class PromoCampaignPlaceholderRenderer
{
    /**
     * @param  array<string, string>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = $value;
        }

        return strtr($template, $replacements);
    }

    /**
     * @return array<string, string>
     */
    public static function forTarget(
        string $name,
        ?string $streetAddress,
        ?string $postalCode,
        ?string $city,
        ?string $email,
        string $promoUrl,
        string $welcomeUrl = '',
    ): array {
        return [
            'name' => $name,
            'street_address' => trim((string) $streetAddress),
            'postal_code' => trim((string) $postalCode),
            'city' => trim((string) $city),
            'email' => trim((string) $email),
            'promo_url' => $promoUrl,
            'welcome_url' => $welcomeUrl,
        ];
    }
}
