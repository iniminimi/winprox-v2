<?php

namespace App\Listeners;

use App\Models\EmailUnsubscribe;
use App\Models\User;
use App\Support\EmailUnsubscribeLink;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AppendEmailUnsubscribeFooterToMessage
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;
        $primary = $this->firstRecipientAddress($message);

        if ($primary === null) {
            return;
        }

        $url = EmailUnsubscribeLink::signedUrl($primary);
        $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $profileHintHtml = '';
        $profileHintText = '';
        if ($this->recipientIsWinProxUser($primary)) {
            $recipientUser = User::query()
                ->whereRaw('LOWER(email) = ?', [EmailUnsubscribe::normalizeEmail($primary)])
                ->first();
            $profileUrl = URL::route('settings.index', [], true);
            $profileUrlEsc = htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8');
            $profileHintHtml = '<div style="text-align:center;margin-top:12px;font-size:12px;color:#6b7280;line-height:1.5;">'
                .str_replace(':url', $profileUrlEsc, __('mail.unsubscribe.users_page_hint_html'))
                .'</div>';
            $profileHintText = "\n".__('mail.unsubscribe.users_page_hint_text', ['url' => $profileUrl])."\n";
        }

        $htmlFooter = '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;line-height:1.5;font-family:Arial,sans-serif;">'
            .'<div style="text-align:center;">'
            .htmlspecialchars(__('mail.unsubscribe.html_intro'), ENT_QUOTES, 'UTF-8')
            .' <a href="'.$urlEsc.'" style="color:#059669;font-weight:600;">'
            .htmlspecialchars(__('mail.unsubscribe.link_label'), ENT_QUOTES, 'UTF-8')
            .'.</div>'
            .$profileHintHtml
            .'<br><br>'
            .'</div>';

        $textFooter = "\n\n---\n".__('mail.unsubscribe.text_intro').' '.$url."\n".$profileHintText;

        $html = $message->getHtmlBody();
        $text = $this->stringifyBody($message->getTextBody());

        if (is_string($html) && $html !== '') {
            $message->html($this->injectBeforeBodyClose($html, $htmlFooter), 'UTF-8');
        }

        if ($text !== '') {
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

    private function recipientIsWinProxUser(string $normalizedEmail): bool
    {
        $normalized = EmailUnsubscribe::normalizeEmail($normalizedEmail);

        return User::query()->whereRaw('LOWER(email) = ?', [$normalized])->exists();
    }

    private function injectBeforeBodyClose(string $html, string $snippet): string
    {
        if (preg_match('/<\/body>/i', $html)) {
            return preg_replace('/<\/body>/i', $snippet.'</body>', $html, 1);
        }

        return $html.$snippet;
    }
}
