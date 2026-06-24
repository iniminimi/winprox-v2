<?php

use App\Actions\Marketing\SendMunicipalPromoLetterEmailAction;
use App\Data\Marketing\MunicipalPromoEmailCandidateData;
use App\Data\Marketing\MunicipalPromoLetterData;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Mail\Marketing\MunicipalPromoLetterMail;
use App\Models\MunicipalPromoEmailSend;
use App\Models\PromoRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function municipalPromoEmailCandidate(
    string $docxPath,
    PromoRecipient $recipient,
    string $email = 'gemeente@example.com',
    ?string $blockReason = null,
): MunicipalPromoEmailCandidateData {
    $municipality = new MunicipalPromoLetterData(
        name: $recipient->label,
        municipalityType: 'Stad',
        streetAddress: 'Gemeentehuisstraat 1',
        postalCode: '9880',
        municipality: $recipient->label,
        province: 'Oost-Vlaanderen',
        phone: null,
        email: $email,
    );

    return new MunicipalPromoEmailCandidateData(
        municipality: $municipality,
        promoRecipientId: $recipient->id,
        promoToken: $recipient->token,
        promoUrl: 'https://winprox.app/promo?ref='.$recipient->token,
        docxPath: $docxPath,
        recipientEmail: $email,
        blockReason: $blockReason,
    );
}

it('verstuurt gemeente-promomail met bijlage en promo-link', function () {
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_1111222233334444',
        'label' => 'Aalter',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $docxPath = storage_path('framework/testing/9880_aalter.docx');
    file_put_contents($docxPath, str_repeat('x', 2048));

    $candidate = municipalPromoEmailCandidate($docxPath, $recipient, 'gemeente@aalter.be');

    app(SendMunicipalPromoLetterEmailAction::class)->handle(
        candidate: $candidate,
        campaign: 'wave-test',
        actorUserId: (int) $superuser->id,
    );

    Mail::assertSent(MunicipalPromoLetterMail::class, function (MunicipalPromoLetterMail $mail): bool {
        return $mail->municipalityName === 'Aalter'
            && str_contains($mail->promoUrl, 'prm_1111222233334444')
            && $mail->envelope()->subject === 'Slimmer beheer van publieke ruimte in Aalter';
    });

    expect(MunicipalPromoEmailSend::query()->where('municipality_name', 'Aalter')->value('status'))
        ->toBe(MunicipalPromoEmailSendStatus::Sent);

    @unlink($docxPath);
});

it('stuurt gemeente-promomail naar override-adres bij test', function () {
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_5555666677778888',
        'label' => 'Aalst',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $docxPath = storage_path('framework/testing/9300_aalst.docx');
    file_put_contents($docxPath, str_repeat('x', 2048));

    $candidate = municipalPromoEmailCandidate($docxPath, $recipient, 'gemeente@aalst.be');

    app(SendMunicipalPromoLetterEmailAction::class)->handle(
        candidate: $candidate,
        campaign: 'wave-test',
        actorUserId: (int) $superuser->id,
        overrideRecipientEmail: 'test@winprox.app',
    );

    Mail::assertSent(MunicipalPromoLetterMail::class, function (MunicipalPromoLetterMail $mail): bool {
        return $mail->hasTo('test@winprox.app');
    });

    @unlink($docxPath);
});

it('weigert dubbele verzending voor dezelfde campagne', function () {
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    $recipient = PromoRecipient::query()->create([
        'token' => 'prm_9999888877776666',
        'label' => 'Dendermonde',
        'note' => null,
        'created_by' => $superuser->id,
    ]);

    $docxPath = storage_path('framework/testing/9200_dendermonde.docx');
    file_put_contents($docxPath, str_repeat('x', 2048));
    $candidate = municipalPromoEmailCandidate($docxPath, $recipient, 'info@dendermonde.be');

    $action = app(SendMunicipalPromoLetterEmailAction::class);
    $action->handle($candidate, 'wave-1', (int) $superuser->id);

    expect(fn () => $action->handle($candidate, 'wave-1', (int) $superuser->id))
        ->toThrow(RuntimeException::class);

    @unlink($docxPath);
});

it('audit commando toont verzendbare gemeenten', function () {
    $path = base_path('tests/Vlaanderen_lokale_besturen.xlsx');
    if (! is_file($path)) {
        $this->markTestSkipped('Spreadsheet fixture ontbreekt lokaal.');
    }

    User::factory()->superuser()->create();

    $this->artisan('marketing:send-municipal-promo-emails', [
        'spreadsheet' => $path,
        '--audit' => true,
        '--limit' => 3,
        '--promo-base-url' => 'https://winprox.test',
    ])->assertSuccessful();
});
