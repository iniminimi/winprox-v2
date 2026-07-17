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
Final-Recipient: rfc822; dominique.schaepdrijver@winprox.app
X-Failed-Recipients: echt-kapot@bedrijf.be
TXT;

    expect(PromoBounceMessageParser::extractRecipientEmails(
        'Undelivered Mail Returned to Sender',
        $body,
    ))->toBe(['echt-kapot@bedrijf.be']);
});
