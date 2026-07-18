<?php

declare(strict_types=1);

use App\Support\Marketing\PromoBounceMessageParser;

it('herkent typische bounce-onderwerpen', function () {
    expect(PromoBounceMessageParser::looksLikeBounce(
        'Undelivered Mail Returned to Sender',
        'MAILER-DAEMON@cloud86.com',
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

it('keurt plausibele ontvangers goed en Message-IDs af', function () {
    expect(PromoBounceMessageParser::isPlausibleRecipientEmail('lammering@trefoil.nl'))->toBeTrue()
        ->and(PromoBounceMessageParser::isPlausibleRecipientEmail(
            '178430534480.3659332.10843687058335425167@shared200.cloud86-host.io',
        ))->toBeFalse()
        ->and(PromoBounceMessageParser::isPlausibleRecipientEmail(
            'b126f9f96778abff0fbddf73cb42a975@winprox.app',
        ))->toBeFalse();
});
