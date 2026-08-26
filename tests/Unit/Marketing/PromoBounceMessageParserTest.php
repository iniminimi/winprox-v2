<?php

declare(strict_types=1);

use App\Support\Marketing\PromoBounceMessageParser;

it('herkent typische bounce-onderwerpen', function () {
    expect(PromoBounceMessageParser::looksLikeBounce(
        'Undelivered Mail Returned to Sender',
        'MAILER-DAEMON@cloud86.com',
    ))->toBeTrue()
        ->and(PromoBounceMessageParser::looksLikeBounce(
            'Mail delivery failed: returning message to sender',
            'MAILER-DAEMON@cloud86.com',
        ))->toBeTrue()
        ->and(PromoBounceMessageParser::looksLikeBounce(
            'Onbestelbaar: uw bericht',
            'postmaster@example.com',
        ))->toBeTrue()
        ->and(PromoBounceMessageParser::looksLikeBounce(
            'Hallo',
            'klant@example.com',
        ))->toBeFalse();
});

it('haalt Final-Recipient uit DSN-body', function () {
    $body = <<<'TXT'
Reporting-MTA: dns; mail.cloud86.com
Final-Recipient: rfc822; broken@example.com
Action: failed
Status: 5.1.1
TXT;

    expect(PromoBounceMessageParser::extractRecipientEmails(
        'Undelivered Mail Returned to Sender',
        $body,
    ))->toBe(['broken@example.com']);
});

it('negeert eigen winprox-afzenders in bounce-body', function () {
    config([
        'winprox.municipal_promo_email_from.address' => 'dominique.schaepdrijver@winprox.app',
        'mail.from.address' => 'info@winprox.app',
    ]);

    $body = <<<'TXT'
Final-Recipient: rfc822; echt-kapot@bedrijf.be
X-Failed-Recipients: echt-kapot@bedrijf.be
TXT;

    expect(PromoBounceMessageParser::extractRecipientEmails(
        'Undelivered Mail Returned to Sender',
        $body,
    ))->toBe(['echt-kapot@bedrijf.be']);
});

it('negeert Message-IDs en hosting-adressen in bounce-body', function () {
    $body = <<<'TXT'
Message-ID: <178430534480.3659332.10843687058335425167@shared200.cloud86-host.io>
Message-ID: <b126f9f96778abff0fbddf73cb42a975@winprox.app>
From: MAILER-DAEMON@shared200.cloud86-host.io
Final-Recipient: rfc822; lammering@trefoil.nl
Action: failed
TXT;

    expect(PromoBounceMessageParser::extractRecipientEmails(
        'Undelivered Mail Returned to Sender',
        $body,
    ))->toBe(['lammering@trefoil.nl']);
});

it('negeert Message-ID als Final-Recipient', function () {
    $body = <<<'TXT'
Final-Recipient: rfc822; 178430724595.3700721.11366882341020174040@shared200.cloud86-host.io
Final-Recipient: rfc822; d7c199cde6016785ae6819e244c85dd2@winprox.app
TXT;

    expect(PromoBounceMessageParser::extractRecipientEmails(
        'Undelivered Mail Returned to Sender',
        $body,
    ))->toBe([]);
});

it('haalt ontvanger uit fallback-zinnen zonder Message-IDs te scrapen', function () {
    $body = <<<'TXT'
Message-ID: <aaaa1111bbbb2222cccc3333dddd4444@winprox.app>
The following address failed: kapot@klant.nl
TXT;

    expect(PromoBounceMessageParser::extractRecipientEmails(
        'Mail Delivery Failed',
        $body,
    ))->toBe(['kapot@klant.nl']);
});

it('classificeert blacklist-, onbekend-adres- en mailbox-vol-bounces', function () {
    $blacklist = <<<'TXT'
<info@hotel.com>: host mail.hotel.com said:
    550 Your host in blacklist on this server.
Diagnostic-Code: smtp; 550 Your host in blacklist on this server.
TXT;

    $unknown = <<<'TXT'
Final-Recipient: rfc822; weg@bedrijf.be
Action: failed
Status: 5.1.1
Diagnostic-Code: smtp; 550 5.1.1 User unknown
TXT;

    $full = <<<'TXT'
Diagnostic-Code: smtp; 552 5.2.2 Mailbox full
TXT;

    expect(PromoBounceMessageParser::classify($blacklist))->toBe(\App\Enums\PromoBounceKind::Blacklist)
        ->and(PromoBounceMessageParser::storageReason($blacklist))->toStartWith('[blacklist]')
        ->and(PromoBounceMessageParser::classify($unknown))->toBe(\App\Enums\PromoBounceKind::Unknown)
        ->and(PromoBounceMessageParser::classify($full))->toBe(\App\Enums\PromoBounceKind::MailboxFull);
});

it('classificeert afgebroken 5.1.1- en 5.7.1-unsolicited-bounces', function () {
    $unknownWrapped = <<<'TXT'
Diagnostic-Code: smtp; 550-5.1.1 The email account that you tried to reach does
    not exist. Please try 550-5.1.1 double-checking the recipient's email
TXT;

    $unsolicited = <<<'TXT'
Diagnostic-Code: smtp; 550 5.7.1 [2026-08-25 10:34:45 CEST] Message blocked as
    is likely unsolicited mail [DFFpCFLVtDvK]
TXT;

    $unsolicitedSecond = <<<'TXT'
Diagnostic-Code: smtp; 550 5.7.1 [2026-08-25 09:25:44 CEST] Message blocked as
    is likely unsolicited mail [QzMnWjmdfgJz]
TXT;

    expect(PromoBounceMessageParser::classify($unknownWrapped))->toBe(\App\Enums\PromoBounceKind::Unknown)
        ->and(PromoBounceMessageParser::classify($unsolicited))->toBe(\App\Enums\PromoBounceKind::Spam)
        ->and(PromoBounceMessageParser::classify($unsolicitedSecond))->toBe(\App\Enums\PromoBounceKind::Spam)
        ->and(PromoBounceMessageParser::storageReason($unsolicited))->toStartWith('[spam]');
});

it('classificeert Spamhaus DBL als domeinblokkade, niet als ontvanger-blacklist', function () {
    $dbl = <<<'TXT'
<email@canblanc.es>: host mx1.spamcluster.com[185.107.213.61] said: 550 An URL
    in this email ( winprox . app ) is listed by Spamhaus DBL. See
    https://check.spamhaus.org/ (in reply to end of DATA command)
Diagnostic-Code: smtp; 550 An URL in this email ( winprox . app ) is listed by
    Spamhaus DBL. See https://check.spamhaus.org/
TXT;

    $hotel = <<<'TXT'
Diagnostic-Code: smtp; 550 Your host in blacklist on this server.
TXT;

    expect(PromoBounceMessageParser::classify($dbl))->toBe(\App\Enums\PromoBounceKind::DomainBlock)
        ->and(PromoBounceMessageParser::storageReason($dbl))->toStartWith('[domain_block]')
        ->and(PromoBounceMessageParser::classify($hotel))->toBe(\App\Enums\PromoBounceKind::Blacklist);
});

it('classificeert Telenet considered-spam als spam, niet als hard bounce', function () {
    $telenet = <<<'TXT'
Diagnostic-Code: smtp; 552 5.2.0 wJiU2H0265FXoZp01JiUtK Your message is
    considered spam
TXT;

    expect(PromoBounceMessageParser::classify($telenet))->toBe(\App\Enums\PromoBounceKind::Spam)
        ->and(PromoBounceMessageParser::storageReason($telenet))->toStartWith('[spam]');
});

it('classificeert enorme of ongeldige MIME zonder te crashen', function () {
    $haystack = str_repeat("\x80\xFF", 80_000)."\n550 User unknown for info@hotel.com\n";

    expect(fn () => PromoBounceMessageParser::storageReason($haystack))->not->toThrow(\Throwable::class)
        ->and(PromoBounceMessageParser::classify($haystack))->toBe(\App\Enums\PromoBounceKind::Unknown);
});

it('keurt plausibele ontvangers goed en Message-IDs af', function () {
    expect(PromoBounceMessageParser::isPlausibleRecipientEmail('lammering@trefoil.nl'))->toBeTrue()
        ->and(PromoBounceMessageParser::isPlausibleRecipientEmail(
            '178430534480.3659332.10843687058335425167@shared200.cloud86-host.io',
        ))->toBeFalse()
        ->and(PromoBounceMessageParser::isPlausibleRecipientEmail(
            'b126f9f96778abff0fbddf73cb42a975@winprox.app',
        ))->toBeFalse();
});

it('haalt Final-Recipient uit DSN-onderdeel als de leesbare body het adres mist', function () {
    $textBody = "I'm afraid I wasn't able to deliver your message.\n";
    $rawBody = <<<'TXT'
Content-Type: multipart/report; report-type=delivery-status

--boundary
Content-Type: text/plain

I'm afraid I wasn't able to deliver your message.
--boundary
Content-Type: message/delivery-status

Final-Recipient: rfc822; kapot@bedrijf.be
Action: failed
Status: 5.1.1
--boundary--
TXT;

    $haystack = PromoBounceMessageParser::haystackFromParts(
        headers: "X-Failed-Recipients: kapot@bedrijf.be\n",
        textBody: $textBody,
        rawBody: $rawBody,
    );

    expect(PromoBounceMessageParser::extractRecipientEmails(
        'Undelivered Mail Returned to Sender',
        $haystack,
    ))->toBe(['kapot@bedrijf.be']);
});
