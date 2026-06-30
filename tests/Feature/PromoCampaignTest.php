<?php

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\CreatePromoRecipientAction;
use App\Actions\Marketing\GeneratePromoCampaignLettersAction;
use App\Actions\Marketing\ImportPromoCampaignSpreadsheetAction;
use App\Actions\Marketing\QueuePromoCampaignEmailsAction;
use App\Actions\Marketing\UpdatePromoCampaignAction;
use App\Data\Marketing\UpdatePromoCampaignData;
use App\Jobs\SendPromoCampaignEmailJob;
use App\Livewire\Platform\PromoCampaignEdit;
use App\Livewire\Platform\PromoCampaigns;
use App\Mail\Marketing\PromoCampaignLetterMail;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignImport;
use App\Models\PromoCampaignTarget;
use App\Models\User;
use App\Support\Marketing\PromoCampaignHtmlSanitizer;
use App\Support\Marketing\PromoCampaignPlaceholderRenderer;
use App\Support\Marketing\PromoCampaignQuillHtmlNormalizer;
use App\Support\Marketing\PromoCampaignSpreadsheetReader;
use App\Support\Marketing\PromoLandingUrl;
use App\Support\Qr\QrCodePngWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function promoCampaignFixturePath(): string
{
    return base_path('tests/fixtures/promo_campaign_sample.xlsx');
}

/**
 * @return array<string, string>
 */
function promoCampaignFixtureMapping(): array
{
    return [
        'name' => 'naam',
        'email' => 'e-mail',
        'street_address' => 'adres',
        'postal_code' => 'postcode',
    ];
}

it('leest spreadsheet met kolommapping', function () {
    $path = promoCampaignFixturePath();
    if (! is_file($path)) {
        $this->markTestSkipped('Promo campaign fixture ontbreekt.');
    }

    $reader = app(PromoCampaignSpreadsheetReader::class);
    $rows = $reader->readRows($path, promoCampaignFixtureMapping());

    expect($rows)->not->toBeEmpty()
        ->and($rows[0]['name'])->not->toBe('');
});

it('importeert ontvangers in campagne', function () {
    $path = promoCampaignFixturePath();
    if (! is_file($path)) {
        $this->markTestSkipped('Promo campaign fixture ontbreekt.');
    }

    $superuser = User::factory()->superuser()->create();

    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'test-wallonie',
        name: 'Wallonie test',
        locale: 'fr',
        actorUserId: (int) $superuser->id,
    );

    $result = app(ImportPromoCampaignSpreadsheetAction::class)->handle(
        campaign: $campaign,
        spreadsheetPath: $path,
        originalFilename: 'sample.xlsx',
        columnMapping: promoCampaignFixtureMapping(),
        actorUserId: (int) $superuser->id,
    );

    expect($result['target_count'])->toBeGreaterThan(0)
        ->and(PromoCampaignTarget::query()->where('promo_campaign_id', $campaign->id)->count())
        ->toBe($result['target_count']);
});

it('toont importbevestiging na excel-upload in livewire', function () {
    $path = promoCampaignFixturePath();
    if (! is_file($path)) {
        $this->markTestSkipped('Promo campaign fixture ontbreekt.');
    }

    $superuser = User::factory()->superuser()->create();

    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'test-import-ui',
        name: 'Import UI test',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
    );

    $file = UploadedFile::fake()->createWithContent(
        'sample.xlsx',
        (string) file_get_contents($path),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    Livewire::actingAs($superuser)
        ->test(PromoCampaignEdit::class, ['promoCampaign' => $campaign])
        ->set('spreadsheet', $file)
        ->set('mapName', 'naam')
        ->set('mapEmail', 'e-mail')
        ->set('mapStreetAddress', 'adres')
        ->set('mapPostalCode', 'postcode')
        ->call('importSpreadsheet')
        ->assertSet('noticeType', 'success')
        ->assertSet('noticeMessage', fn ($message) => str_contains((string) $message, 'sample.xlsx'));
});

it('genereert docx met paragraaf-spacing in het middenstuk', function () {
    if (! QrCodePngWriter::canGenerate()) {
        $this->markTestSkipped('QR generation unavailable.');
    }

    $superuser = User::factory()->superuser()->create();

    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'test-docx-spacing',
        name: 'DOCX spacing',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
    );

    app(UpdatePromoCampaignAction::class)->handle(
        campaign: $campaign,
        data: new UpdatePromoCampaignData(
            name: 'DOCX spacing',
            locale: 'nl',
            letterBodyHtml: '<p><strong>WinProx</strong>: intro.</p><p><br></p><p>Tweede alinea.</p>'
                .'<ol><li data-list="bullet">Punt één</li><li data-list="bullet">Punt twee</li></ol>',
            emailSubject: null,
            emailBodyHtml: null,
            flowImagePath: null,
            columnMapping: null,
        ),
        actorUserId: (int) $superuser->id,
    );

    $import = PromoCampaignImport::query()->create([
        'promo_campaign_id' => $campaign->id,
        'original_filename' => 'one.xlsx',
        'row_count' => 1,
        'imported_by' => $superuser->id,
        'imported_at' => now(),
    ]);

    $recipient = app(CreatePromoRecipientAction::class)->handle('Spacingstad', null, (int) $superuser->id);

    PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $campaign->id,
        'promo_campaign_import_id' => $import->id,
        'promo_recipient_id' => $recipient->id,
        'name' => 'Spacingstad',
        'email' => 'test@example.com',
        'street_address' => 'Straat 1',
        'postal_code' => '1000',
        'city' => 'Spacingstad',
    ]);

    app(GeneratePromoCampaignLettersAction::class)->handle(
        campaign: $campaign->fresh(),
        actorUserId: (int) $superuser->id,
        promoBaseUrl: 'https://winprox.test',
        overwriteExisting: true,
        limit: 1,
    );

    $target = PromoCampaignTarget::query()
        ->where('promo_campaign_id', $campaign->id)
        ->whereNotNull('generated_at')
        ->first();

    $docxPath = $campaign->fresh()->lettersDirectory().DIRECTORY_SEPARATOR.$target->docx_filename;
    $zip = new ZipArchive();
    expect($zip->open($docxPath))->toBeTrue();
    $documentXml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    expect($documentXml)
        ->toContain('WinProx')
        ->toContain('Tweede alinea')
        ->toContain('Punt één')
        ->toContain('w:after="240"')
        ->toContain('w:after="80"');

    @unlink($docxPath);
});

it('genereert docx voor campagne-ontvanger', function () {
    if (! QrCodePngWriter::canGenerate()) {
        $this->markTestSkipped('QR generation unavailable.');
    }

    $path = promoCampaignFixturePath();
    if (! is_file($path)) {
        $this->markTestSkipped('Promo campaign fixture ontbreekt.');
    }

    $superuser = User::factory()->superuser()->create();

    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'test-generate',
        name: 'Generate test',
        locale: 'fr',
        actorUserId: (int) $superuser->id,
    );

    app(UpdatePromoCampaignAction::class)->handle(
        campaign: $campaign,
        data: new UpdatePromoCampaignData(
            name: 'Generate test',
            locale: 'fr',
            letterBodyHtml: '<p>Intro {{name}}</p><p>Les avantages :</p><ol><li data-list="bullet">Punt één</li><li data-list="bullet">Punt twee</li></ol><p>Afsluiting.</p>',
            emailSubject: 'Test {{name}}',
            emailBodyHtml: '<p>Email {{name}}</p>',
            flowImagePath: 'public/images/promo/flow_fr.jpg',
            columnMapping: null,
        ),
        actorUserId: (int) $superuser->id,
    );

    app(ImportPromoCampaignSpreadsheetAction::class)->handle(
        campaign: $campaign->fresh(),
        spreadsheetPath: $path,
        originalFilename: 'sample.xlsx',
        columnMapping: promoCampaignFixtureMapping(),
        actorUserId: (int) $superuser->id,
    );

    $result = app(GeneratePromoCampaignLettersAction::class)->handle(
        campaign: $campaign->fresh(),
        actorUserId: (int) $superuser->id,
        promoBaseUrl: 'https://winprox.test',
        overwriteExisting: true,
        limit: 1,
    );

    expect($result['generated'])->toBe(1);

    $target = PromoCampaignTarget::query()
        ->where('promo_campaign_id', $campaign->id)
        ->whereNotNull('generated_at')
        ->first();
    expect($target?->generated_at)->not->toBeNull();

    $docxPath = $campaign->fresh()->lettersDirectory().DIRECTORY_SEPARATOR.$target->docx_filename;
    expect(is_file($docxPath))->toBeTrue();

    $zip = new ZipArchive();
    expect($zip->open($docxPath))->toBeTrue();
    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();
    expect($documentXml)->toBeString()
        ->and($documentXml)->toContain('Intro')
        ->and($documentXml)->toContain('Punt één')
        ->and($documentXml)->toContain('<w:numPr>')
        ->and(substr_count($documentXml, '<w:p '))->toBeLessThan(35);

    @unlink($docxPath);
});

it('vervangt placeholders', function () {
    $html = PromoCampaignPlaceholderRenderer::render(
        'Bonjour {{name}} à {{city}}',
        ['name' => 'Amay', 'city' => 'Amay'],
    );

    expect($html)->toBe('Bonjour Amay à Amay');
});

it('behandelt lege quill-html als leeg', function () {
    expect(PromoCampaignHtmlSanitizer::clean('<p></p>'))->toBeNull()
        ->and(PromoCampaignHtmlSanitizer::clean('<p><br></p>'))->toBeNull()
        ->and(PromoCampaignHtmlSanitizer::clean('<p>Hallo</p>'))->toBe('<p>Hallo</p>');
});

it('herstelt dubbel ge-escaped editor html', function () {
    $encoded = '&lt;p&gt;Dit is een test brief&lt;/p&gt;&lt;p&gt;{{name}}&lt;/p&gt;';

    expect(PromoCampaignHtmlSanitizer::forEditor($encoded))
        ->toBe('<p>Dit is een test brief</p><p>{{name}}</p>');
});

it('normaliseert quill html voor docx en mail', function () {
    $html = '<p>Dit is een test</p><p><br></p><ol><li data-list="bullet">1</li><li data-list="bullet">2</li></ol>';

    expect(PromoCampaignQuillHtmlNormalizer::normalize($html))
        ->toBe('<p>Dit is een test</p><p><br/></p><ul><li>1</li><li>2</li></ul>');
});

it('verwijdert lege quill-paragrafen uit e-mail body', function () {
    $html = '<p>Madame, Monsieur,</p><p><br></p>'
        .'<p>La gestion des infrastructures.</p><p><br/></p>'
        .'<p>Sans installation d\'application.</p>';

    expect(PromoCampaignQuillHtmlNormalizer::forMail($html))
        ->toBe(
            '<p style="margin:0 0 16px 0">Madame, Monsieur,</p>'
            .'<p style="margin:0 0 16px 0">La gestion des infrastructures.</p>'
            .'<p style="margin:0 0 16px 0">Sans installation d\'application.</p>',
        );
});

it('zet platte tekst om naar gescheiden e-mail paragrafen', function () {
    $text = "Madame, Monsieur,\n"
        ."Veuillez trouver ci-joint une présentation de WinProx.\n"
        ."Sans installation d'application, les citoyens peuvent signaler un problème.\n"
        ."Cordialement,";

    $prepared = PromoCampaignQuillHtmlNormalizer::forMail($text);

    expect($prepared)
        ->toContain('<p style="margin:0 0 16px 0">Madame, Monsieur,</p>')
        ->toContain('<p style="margin:0 0 16px 0">Veuillez trouver ci-joint une présentation de WinProx.</p>')
        ->toContain('<p style="margin:0 0 16px 0">Cordialement,</p>');
});

it('splitst enkele quill-paragraaf met br-tags voor e-mail', function () {
    $html = '<p>Madame, Monsieur,<br>Veuillez trouver ci-joint.<br>Sans installation.</p>';

    expect(PromoCampaignQuillHtmlNormalizer::forMail($html))
        ->toBe(
            '<p style="margin:0 0 16px 0">Madame, Monsieur,</p>'
            .'<p style="margin:0 0 16px 0">Veuillez trouver ci-joint.</p>'
            .'<p style="margin:0 0 16px 0">Sans installation.</p>',
        );
});

it('bereidt volledige quill-brief voor op docx zonder dubbele blokken', function () {
    $html = '<p>Wavre</p><p>place de l\'Hôtel de Ville</p><p>1300 Wavre</p><p><br></p>'
        .'<p>Madame, Monsieur,</p><p><br></p>'
        .'<p>La gestion des infrastructures publiques.</p><p><br></p>'
        .'<p>Le principe est très simple :</p>'
        .'<p>Dans l\'attente de votre retour, je vous prie d\'agréer, Madame, Monsieur, l\'expression de mes salutations distinguées.</p>'
        .'<p>Dominique Schaepdrijver</p>';

    $prepared = PromoCampaignQuillHtmlNormalizer::forDocx($html, 'fr');

    expect($prepared)
        ->toContain('La gestion des infrastructures publiques')
        ->toContain('Le principe est très simple')
        ->not->toContain('Madame, Monsieur')
        ->not->toContain('Dans l\'attente')
        ->not->toContain('Dominique Schaepdrijver')
        ->not->toContain('1300 Wavre');
});

it('behoudt quill-lijsten voor docx met ul-li structuur', function () {
    $html = '<p>Les avantages pour votre commune :</p>'
        .'<ol><li data-list="bullet"><span class="ql-ui"></span><strong>Gestion centralisée</strong> : Tous les signalements.</li>'
        .'<li data-list="bullet"><span class="ql-ui"></span>Entretien optimisé : Les équipes.</li></ol>'
        .'<p>Je serais ravi.</p>';

    $prepared = PromoCampaignQuillHtmlNormalizer::forDocx($html, 'fr');

    expect($prepared)
        ->toContain('<ul><li>')
        ->not->toContain('<ol>')
        ->not->toContain('<p>•');
});

it('bewaart lege regels in brief-middenstuk voor docx', function () {
    $html = '<p>Eerste blok</p><p><br></p><p>Tweede blok</p>';

    expect(PromoCampaignQuillHtmlNormalizer::forDocx($html, 'nl'))
        ->toContain('<p><br')
        ->toContain('Eerste blok')
        ->toContain('Tweede blok');
});

it('zet quill-ol zonder data-list om naar ul voor docx', function () {
    $html = '<p>Les avantages :</p><ol><li><strong>Gestion</strong> : foo.</li><li>Bar.</li></ol>';

    expect(PromoCampaignQuillHtmlNormalizer::forDocx($html, 'fr'))
        ->toContain('<ul><li><strong>Gestion</strong>')
        ->not->toContain('<ol>');
});

it('wist brief-middenstuk niet weg bij aanhef diep in de tekst', function () {
    $html = '<p>Intro.</p><p>Les avantages :</p>'
        .'<ol><li data-list="bullet">Punt één</li></ol>'
        .'<p>Je vous prie d\'agréer, Madame, Monsieur, mes salutations.</p>';

    $prepared = PromoCampaignQuillHtmlNormalizer::forDocx($html, 'fr');

    expect($prepared)
        ->toContain('Intro.')
        ->toContain('Punt één')
        ->toContain('Je vous prie');
});

it('laat superuser promo-campagnes beheren', function () {
    $superuser = User::factory()->superuser()->create();

    $this->actingAs($superuser)
        ->get(route('platform.promo-campaigns'))
        ->assertOk()
        ->assertSee(__('platform.promo_campaigns.title'));

    Livewire::actingAs($superuser)
        ->test(PromoCampaigns::class)
        ->set('slug', 'Wallonie-Wave-1')
        ->set('name', 'Livewire campagne')
        ->set('locale', 'fr')
        ->call('createCampaign')
        ->assertHasNoErrors();

    expect(PromoCampaign::query()->where('slug', 'wallonie-wave-1')->exists())->toBeTrue();
});

it('bewaart lege regels in e-mailtekst bij opslaan campagne', function () {
    $superuser = User::factory()->superuser()->create();

    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'test-email-blanks',
        name: 'Email blanks',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
    );

    $emailHtml = '<p>Eerste alinea</p><p><br></p><p>Tweede alinea</p>';

    app(UpdatePromoCampaignAction::class)->handle(
        campaign: $campaign,
        data: new UpdatePromoCampaignData(
            name: 'Email blanks',
            locale: 'nl',
            letterBodyHtml: null,
            emailSubject: 'Onderwerp',
            emailBodyHtml: $emailHtml,
            flowImagePath: null,
            columnMapping: null,
        ),
        actorUserId: (int) $superuser->id,
    );

    expect($campaign->fresh()->email_body_html)
        ->toContain('<p><br')
        ->toContain('Eerste alinea')
        ->toContain('Tweede alinea')
        ->not->toContain('margin:0 0 16px');
});

it('slaat promo-campagne op via edit-pagina', function () {
    $superuser = User::factory()->superuser()->create();

    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'test-save',
        name: 'Save test',
        locale: 'fr',
        actorUserId: (int) $superuser->id,
    );

    Livewire::actingAs($superuser)
        ->test(PromoCampaignEdit::class, ['promoCampaign' => $campaign])
        ->set('name', 'Save test bijgewerkt')
        ->set('mapName', 'nom')
        ->set('mapEmail', 'email_general')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('noticeMessage', __('platform.promo_campaigns.saved'))
        ->assertSee(__('platform.promo_campaigns.saved'));

    expect($campaign->fresh())
        ->name->toBe('Save test bijgewerkt')
        ->letter_body_html->toBeNull()
        ->column_mapping->toMatchArray([
            'name' => 'nom',
            'email' => 'email_general',
        ]);
});

it('toont duidelijke fout bij ongeldige campagne-slug', function () {
    $superuser = User::factory()->superuser()->create();

    Livewire::actingAs($superuser)
        ->test(PromoCampaigns::class)
        ->set('slug', 'ongeldige slug')
        ->set('name', 'Test')
        ->set('locale', 'fr')
        ->call('createCampaign')
        ->assertHasErrors(['slug'])
        ->assertSee(__('platform.promo_campaigns.slug_invalid'));
});

it('blokkeert promo-campagnes voor normale gebruikers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('platform.promo-campaigns'))
        ->assertForbidden();
});

it('rendert promo-campagne e-mail als html zonder view-fout', function () {
    $bodyHtml = PromoCampaignQuillHtmlNormalizer::forMail(
        '<p>Madame, Monsieur,</p><p>La gestion des infrastructures.</p>',
    );

    $html = (new PromoCampaignLetterMail(
        emailSubject: 'WinProx pour Wavre',
        emailBodyHtml: $bodyHtml,
        docxPath: __FILE__,
        mailLocale: 'fr',
    ))->render();

    expect($html)
        ->toContain('Madame, Monsieur')
        ->toContain('La gestion des infrastructures')
        ->toContain('email-wrapper')
        ->toContain('Winprox_logo_100.png')
        ->toContain('margin:0 0 16px 0');
});

it('voegt campagne-locale toe aan promo-landing-url', function () {
    expect(PromoLandingUrl::forRecipientTokenOnBaseUrl('prm_4cfe5ddb16702059', 'https://winprox.app', 'fr'))
        ->toBe('https://winprox.app/promo?ref=prm_4cfe5ddb16702059&lang=fr');
});

it('opent promo-pagina in campagne-locale via ref-link', function () {
    $superuser = User::factory()->superuser()->create();
    $recipient = app(CreatePromoRecipientAction::class)->handle('Wavre', null, (int) $superuser->id);
    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'wavre-fr',
        name: 'Wavre',
        locale: 'fr',
        actorUserId: (int) $superuser->id,
    );

    $import = PromoCampaignImport::query()->create([
        'promo_campaign_id' => $campaign->id,
        'original_filename' => 'wavre.xlsx',
        'row_count' => 1,
        'imported_by' => $superuser->id,
        'imported_at' => now(),
    ]);

    PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $campaign->id,
        'promo_campaign_import_id' => $import->id,
        'promo_recipient_id' => $recipient->id,
        'name' => 'Wavre',
        'email' => 'test@example.com',
        'street_address' => 'Place de l\'Hôtel de Ville',
        'postal_code' => '1300',
        'city' => 'Wavre',
        'generated_at' => now(),
    ]);

    $this->get(route('promo', ['ref' => $recipient->token]))
        ->assertOk()
        ->assertSee(__('promo.video.qr_portal.title', [], 'fr'), false);
});

/**
 * @return array{0: PromoCampaign, 1: PromoCampaignTarget}
 */
function promoCampaignReadyForEmail(User $superuser, string $excelEmail = 'gemeente@example.com'): array
{
    $recipient = app(CreatePromoRecipientAction::class)->handle('Testgemeente', null, (int) $superuser->id);
    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'queue-test',
        name: 'Queue test',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
    );

    app(UpdatePromoCampaignAction::class)->handle(
        campaign: $campaign,
        data: new UpdatePromoCampaignData(
            name: 'Queue test',
            locale: 'nl',
            letterBodyHtml: '<p>Brief {{name}}</p>',
            emailSubject: 'Test {{name}}',
            emailBodyHtml: '<p>Email {{name}}</p>',
            flowImagePath: null,
            columnMapping: null,
        ),
        actorUserId: (int) $superuser->id,
    );

    $import = PromoCampaignImport::query()->create([
        'promo_campaign_id' => $campaign->id,
        'original_filename' => 'test.xlsx',
        'row_count' => 1,
        'imported_by' => $superuser->id,
        'imported_at' => now(),
    ]);

    $docxFilename = 'test-brief.docx';
    $lettersDir = $campaign->fresh()->lettersDirectory();
    if (! is_dir($lettersDir)) {
        mkdir($lettersDir, 0755, true);
    }
    file_put_contents($lettersDir.DIRECTORY_SEPARATOR.$docxFilename, 'docx');

    $target = PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $campaign->id,
        'promo_campaign_import_id' => $import->id,
        'promo_recipient_id' => $recipient->id,
        'name' => 'Testgemeente',
        'email' => $excelEmail,
        'street_address' => 'Straat 1',
        'postal_code' => '1000',
        'city' => 'Brussel',
        'docx_filename' => $docxFilename,
        'generated_at' => now(),
    ]);

    return [$campaign->fresh(), $target];
}

it('zet bulk-mails in wachtrij naar excel-adressen zonder testadres', function () {
    Queue::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmail($superuser, 'gemeente@example.com');

    app(QueuePromoCampaignEmailsAction::class)->handle(
        campaign: $campaign,
        actorUserId: (int) $superuser->id,
        delaySeconds: 0,
    );

    Queue::assertPushed(SendPromoCampaignEmailJob::class, function (SendPromoCampaignEmailJob $job): bool {
        return $job->overrideRecipientEmail === null;
    });
});

it('toont bevestigingspopup met aantal mails voor bulk verzenden', function () {
    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmail($superuser);

    Livewire::actingAs($superuser)
        ->test(PromoCampaignEdit::class, ['promoCampaign' => $campaign])
        ->set('testEmailTo', 'test@winprox.app')
        ->call('openQueueConfirm')
        ->assertSet('showQueueConfirm', true)
        ->assertSet('queueConfirmQueued', 1)
        ->assertSee(__('platform.promo_campaigns.queue_confirm_body', ['count' => 1]));
});

it('bevestigt bulk verzenden zonder testadres mee te nemen', function () {
    Queue::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmail($superuser, 'gemeente@example.com');

    Livewire::actingAs($superuser)
        ->test(PromoCampaignEdit::class, ['promoCampaign' => $campaign])
        ->set('testEmailTo', 'test@winprox.app')
        ->call('openQueueConfirm')
        ->call('confirmQueueEmails')
        ->assertSet('showQueueConfirm', false);

    Queue::assertPushed(SendPromoCampaignEmailJob::class, function (SendPromoCampaignEmailJob $job): bool {
        return $job->overrideRecipientEmail === null;
    });
});

it('stuurt testmail alleen naar ingevuld testadres', function () {
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmail($superuser, 'gemeente@example.com');

    Livewire::actingAs($superuser)
        ->test(PromoCampaignEdit::class, ['promoCampaign' => $campaign])
        ->set('testEmailTo', 'test@winprox.app')
        ->call('sendTestEmail')
        ->assertSet('noticeType', 'success');

    Mail::assertSent(PromoCampaignLetterMail::class, function (PromoCampaignLetterMail $mail): bool {
        return $mail->hasTo('test@winprox.app');
    });
});
