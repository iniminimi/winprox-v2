<?php

namespace App\Listeners;

use App\Models\EmailUnsubscribe;
use App\Models\User;
use App\Support\EmailUnsubscribeLink;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AppendEmailUnsubscribeFooterToMessage
{
    public function handle(MessageSending $event): void
    {
        if ($event->message->getHeaders()->has('X-WinProx-Transactional')) {
            return;
        }

        $message = $event->message;
        $primary = $this->firstRecipientAddress($message);

        if ($primary === null) {
            return;
        }

        $url = EmailUnsubscribeLink::signedUrl($primary);
        $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $htmlIntro = htmlspecialchars(__('mail.unsubscribe.html_intro'), ENT_QUOTES, 'UTF-8');
        $linkLabel = htmlspecialchars(__('mail.unsubscribe.link_label'), ENT_QUOTES, 'UTF-8');

        $settingsUrl = route('settings.index');
        $settingsUrlEsc = htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8');
        $hasAccount = User::query()->where('email', $primary)->exists();

        $htmlFooter = '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;line-height:1.5;font-family:Arial,sans-serif;">'
            .'<div style="text-align:center;">'
            .$htmlIntro
            .' <a href="'.$urlEsc.'" style="color:#059669;font-weight:600;">'
            .$linkLabel
            .'</a>.</div>';

        if ($hasAccount) {
            $htmlFooter .= '<div style="text-align:center;margin-top:8px;">'
                .__('mail.unsubscribe.settings_hint_html', ['url' => $settingsUrlEsc])
                .'</div>';
        }

        $htmlFooter .= '</div>';

        $textFooter = "\n\n---\n".__('mail.unsubscribe.text_intro').' '.$url;
        if ($hasAccount) {
            $textFooter .= "\n".__('mail.unsubscribe.settings_hint_text', ['url' => $settingsUrl]);
        }
        $textFooter .= "\n";

        $html = $message->getHtmlBody();
        $text = $this->stringifyBody($message->getTextBody());

        if (is_string($html) && $html !== '' && ! str_contains($html, $htmlIntro)) {
            $message->html($this->injectBeforeBodyClose($html, $htmlFooter), 'UTF-8');
        }

        if ($text !== '' && ! str_contains($text, __('mail.unsubscribe.text_intro'))) {
            $message->text($text.$textFooter, 'UTF-8');
        }

        if ((! is_string($html) || $html === '') && $text === '') {
            $message->text(ltrim($textFooter), 'UTF-8');
        }

        $headers = $message->getHeaders();
        if (! $headers->has('List-Unsubscribe')) {
            $headers->addTextHeader('List-Unsubscribe', '<'.$url.'>');
        }
    }

    private function stringifyBody(mixed $body): string
    {
        if (is_string($body)) {
            return $body;
        }

        if (is_resource($body)) {
            $read = stream_get_contents($body);
            if (is_string($read)) {
                return $read;
            }
        }

        return '';
    }

    /**
     * Eerste ontvanger voor uitschrijf-URL (To, anders Cc, anders Bcc).
     * Altijd toegepast — ook voor "interne" adressen — voor deliverability / spamfilters.
     */
    private function firstRecipientAddress(Email $message): ?string
    {
        foreach ([$message->getTo(), $message->getCc(), $message->getBcc()] as $list) {
            foreach ($list as $addr) {
                if (! $addr instanceof Address) {
                    continue;
                }

                $email = EmailUnsubscribe::normalizeEmail($addr->getAddress());
                if ($email !== '') {
                    return $email;
                }
            }
        }

        return null;
    }

    private function injectBeforeBodyClose(string $html, string $snippet): string
    {
        if (preg_match('/<\/body>/i', $html)) {
            return preg_replace('/<\/body>/i', $snippet.'</body>', $html, 1);
        }

        return $html.$snippet;
    }
}
