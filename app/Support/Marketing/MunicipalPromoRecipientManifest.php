<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use RuntimeException;

final class MunicipalPromoRecipientManifest
{
    /**
     * @param  array<string, string>  $recipients  gemeentenaam => promo-token
     */
    public function __construct(
        public readonly string $promoAppUrl,
        public readonly array $recipients,
    ) {}

    public static function read(string $absolutePath): self
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Manifest not found: {$absolutePath}");
        }

        $decoded = json_decode((string) file_get_contents($absolutePath), true);
        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid manifest JSON: {$absolutePath}");
        }

        $promoAppUrl = rtrim((string) ($decoded['promo_app_url'] ?? ''), '/');
        if ($promoAppUrl === '') {
            throw new RuntimeException('Manifest is missing promo_app_url.');
        }

        $recipients = $decoded['recipients'] ?? null;
        if (! is_array($recipients)) {
            throw new RuntimeException('Manifest is missing recipients map.');
        }

        $normalized = [];
        foreach ($recipients as $label => $token) {
            $label = trim((string) $label);
            $token = PromoRecipientToken::normalize((string) $token);
            if ($label === '' || $token === '') {
                continue;
            }

            $normalized[$label] = $token;
        }

        return new self($promoAppUrl, $normalized);
    }

    public function write(string $absolutePath): void
    {
        $directory = dirname($absolutePath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create manifest directory: {$directory}");
        }

        ksort($this->recipients, SORT_NATURAL | SORT_FLAG_CASE);

        $payload = [
            'promo_app_url' => $this->promoAppUrl,
            'recipients' => $this->recipients,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($absolutePath, $json) === false) {
            throw new RuntimeException("Unable to write manifest: {$absolutePath}");
        }
    }

    public function tokenFor(string $label): ?string
    {
        return $this->recipients[$label] ?? null;
    }

    public function withRecipient(string $label, string $token): self
    {
        $recipients = $this->recipients;
        $recipients[$label] = $token;

        return new self($this->promoAppUrl, $recipients);
    }

    public function promoUrlForToken(string $token): string
    {
        return PromoLandingUrl::forRecipientTokenOnBaseUrl($token, $this->promoAppUrl);
    }
}
