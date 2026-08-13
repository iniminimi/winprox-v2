<?php

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\CreatePromoRecipientAction;
use App\Actions\Marketing\GeneratePromoCampaignLettersAction;
use App\Actions\Marketing\ImportPromoCampaignSpreadsheetAction;
use App\Actions\Marketing\QueuePromoCampaignEmailsAction;
use App\Actions\Marketing\RecordPromoVisitAction;
use App\Actions\Marketing\SendPromoCampaignEmailAction;
use App\Actions\Marketing\UpdatePromoCampaignAction;
use App\Data\Marketing\UpdatePromoCampaignData;
use App\Enums\MunicipalPromoEmailSendStatus;
use App\Enums\PromoVisitPage;
use App\Jobs\SendPromoCampaignEmailJob;
use App\Livewire\Platform\PromoCampaignEdit;
use App\Livewire\Platform\PromoCampaigns;
use App\Mail\Marketing\PromoCampaignLetterMail;
use App\Models\EmailUnsubscribe;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignEmailSend;
use App\Models\PromoCampaignImport;
use App\Models\PromoCampaignTarget;
use App\Models\PromoRecipient;
use App\Models\User;
use App\Support\Marketing\PromoCampaignHtmlSanitizer;
use App\Support\Marketing\PromoCampaignLetterDocxBuilder;
use App\Support\Marketing\PromoCampaignPlaceholderRenderer;
use App\Support\Marketing\PromoCampaignQuillHtmlNormalizer;
use App\Support\Marketing\PromoCampaignSpreadsheetReader;
use App\Support\Marketing\PromoLandingUrl;
use App\Support\Qr\QrCodePngWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
            attachLetterToEmail: true,
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
        ->toContain('w:after="24"');

    @unlink($docxPath);
});

it('start elke bullet-lijst opnieuw na tussenliggende alinea', function () {
    if (! QrCodePngWriter::canGenerate()) {
        $this->markTestSkipped('QR generation unavailable.');
    }

    $html = PromoCampaignQuillHtmlNormalizer::forDocx(
        '<p>Eerste lijst:</p><ul><li>Punt A</li><li>Punt B</li></ul><p><br></p><p>Tussenkop</p>'
        .'<ul><li>Punt C</li><li>Punt D</li></ul>',
        'nl',
    );

    $path = storage_path('framework/testing/bullet-restart.docx');
    app(PromoCampaignLetterDocxBuilder::class)->build(
        'nl',
        ['name' => 'T', 'street_address' => 'S', 'postal_code' => '1', 'city' => 'B', 'email' => '', 'promo_url' => 'https://x.test'],
        $html,
        null,
        'https://x.test',
        $path,
    );

    $zip = new ZipArchive();
    expect($zip->open($path))->toBeTrue();
    $documentXml = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    preg_match_all('/<w:numPr><w:ilvl w:val="0"\/><w:numId w:val="(\d+)"\/><\/w:numPr>/', $documentXml, $matches);
    expect(array_values(array_unique($matches[1] ?? [])))->toHaveCount(2);

    @unlink($path);
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
            attachLetterToEmail: true,
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
        'Bonjour {{name}} à {{city}} — {{welcome_url}}',
        [
            'name' => 'Amay',
            'city' => 'Amay',
            'welcome_url' => 'https://winprox.app/nl/?ref=prm_abcdef0123456789',
        ],
    );

    expect($html)->toBe('Bonjour Amay à Amay — https://winprox.app/nl/?ref=prm_abcdef0123456789');
});

it('levert welcome_url in forTarget', function () {
    $vars = PromoCampaignPlaceholderRenderer::forTarget(
        name: 'Amay',
        streetAddress: 'Rue 1',
        postalCode: '1000',
        city: 'Amay',
        email: 'a@example.test',
        promoUrl: 'https://winprox.app/nl/promo?ref=prm_abcdef0123456789',
        welcomeUrl: 'https://winprox.app/nl/?ref=prm_abcdef0123456789',
    );

    expect($vars['welcome_url'])->toBe('https://winprox.app/nl/?ref=prm_abcdef0123456789')
        ->and($vars['promo_url'])->toContain('/promo?ref=');
});

it('behandelt lege quill-html als leeg', function () {
    expect(PromoCampaignHtmlSanitizer::clean('<p></p>'))->toBeNull()
        ->and(PromoCampaignHtmlSanitizer::clean('<p><br></p>'))->toBeNull()
        ->and(PromoCampaignHtmlSanitizer::clean('<p>Hallo</p>'))->toBe('<p>Hallo</p>');
});

it('behoudt promo-url placeholders als link in de editor', function () {
    $html = '<p><a href="{{promo_url}}">Klik hier</a> '
        .'<a href="{{welcome_url}}">Welcome</a> '
        .'<a href="http://{{promo_url}}">Met protocol</a> '
        .'<a href="javascript:alert(1)">Nee</a></p>';

    expect(PromoCampaignHtmlSanitizer::clean($html))
        ->toBe(
            '<p><a href="{{promo_url}}">Klik hier</a> '
            .'<a href="{{welcome_url}}">Welcome</a> '
            .'<a href="{{promo_url}}">Met protocol</a> '
            .'<a>Nee</a></p>',
        );

    expect(PromoCampaignHtmlSanitizer::forEditor($html))
        ->toContain('href="{{promo_url}}"')
        ->toContain('href="{{welcome_url}}"');
});

it('behoudt lettergrootte en veilige links in promo html', function () {
    $html = '<p><span style="font-size: 18px; color: red;">Hallo</span> '
        .'<a href="https://winprox.app" onclick="alert(1)">site</a></p>'
        .'<p><span class="ql-size-huge">Kop</span></p>';

    expect(PromoCampaignHtmlSanitizer::clean($html))
        ->toBe(
            '<p><span style="font-size: 18px">Hallo</span> '
            .'<a href="https://winprox.app">site</a></p>'
            .'<p><span style="font-size: 28px">Kop</span></p>',
        );
});

it('zet lettergrootte om naar inline stijl in promo e-mail', function () {
    $html = '<p><span style="font-size: 22px;">WinProx</span></p>';

    expect(PromoCampaignQuillHtmlNormalizer::forMail($html))
        ->toBe('<p style="margin:0 0 16px 0;font-size: 22px"><span style="font-size: 22px">WinProx</span></p>');
});

it('behoudt lettergrootte op vetgedrukte promo-regels', function () {
    $html = '<p><strong style="font-size: 18px;">Geen app. Geen installatie.</strong></p>';

    expect(PromoCampaignHtmlSanitizer::clean($html))
        ->toBe('<p><strong style="font-size: 18px">Geen app. Geen installatie.</strong></p>');

    expect(PromoCampaignQuillHtmlNormalizer::forMail($html))
        ->toBe('<p style="margin:0 0 16px 0;font-size: 18px"><strong style="font-size: 18px">Geen app. Geen installatie.</strong></p>');
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

it('compacteert handtekeningregels in promo e-mail', function () {
    $html = '<p>Goedemorgen,</p><p><br></p>'
        .'<p>Voor {{name}} ben ik op zoek.</p><p><br></p>'
        .'<p>Alvast hartelijk bedankt voor uw hulp.</p><p><br></p>'
        .'<p>Met vriendelijke groeten,</p><p><br></p><p><br></p>'
        .'<p>Schaepdrijver Dominique</p><p>Founder WinProx</p>'
        .'<p>www.winprox.app</p><p>dominique.schaepdrijver@winprox.app</p>';

    $prepared = PromoCampaignQuillHtmlNormalizer::forMail($html);

    expect($prepared)
        ->toContain('<p style="margin:0 0 24px 0">Met vriendelijke groeten,</p>')
        ->toContain('<p style="margin:0">Schaepdrijver Dominique<br>Founder WinProx<br>www.winprox.app<br>dominique.schaepdrijver@winprox.app</p>')
        ->not->toMatch('/Founder WinProx<\/p>\s*<p style="margin:0 0 16px 0">www\.winprox\.app/');
});

it('verwijdert alleen vast handtekeningblok uit brief voor docx', function () {
    $html = '<p>Wavre</p><p>place de l\'Hôtel de Ville</p><p>1300 Wavre</p><p><br></p>'
        .'<p>Madame, Monsieur,</p><p><br></p>'
        .'<p>La gestion des infrastructures publiques.</p><p><br></p>'
        .'<p>Le principe est très simple :</p>'
        .'<p>Dans l\'attente de votre retour, je vous prie d\'agréer, Madame, Monsieur, l\'expression de mes salutations distinguées.</p>'
        .'<p>Dominique Schaepdrijver</p>';

    $prepared = PromoCampaignQuillHtmlNormalizer::forDocx($html, 'fr');

    expect($prepared)
        ->toContain('1300 Wavre')
        ->toContain('Madame, Monsieur')
        ->toContain('La gestion des infrastructures publiques')
        ->toContain('Le principe est très simple')
        ->not->toContain('Dans l\'attente')
        ->not->toContain('Dominique Schaepdrijver');
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

it('wist aanhef in brief-middenstuk niet weg wanneer diep in de tekst', function () {
    $html = '<p>Intro.</p><p>Les avantages :</p>'
        .'<ol><li data-list="bullet">Punt één</li></ol>'
        .'<p>Je vous prie d\'agréer, Madame, Monsieur, mes salutations.</p>';

    $prepared = PromoCampaignQuillHtmlNormalizer::forDocx($html, 'fr');

    expect($prepared)
        ->toContain('Intro.')
        ->toContain('Punt één')
        ->toContain('Je vous prie');
});

it('kopieert promo-campagne naar nieuwe campagne', function () {
    $superuser = User::factory()->superuser()->create();

    $source = app(CreatePromoCampaignAction::class)->handle(
        slug: 'source-wave',
        name: 'Broncampagne',
        locale: 'fr',
        actorUserId: (int) $superuser->id,
    );

    app(UpdatePromoCampaignAction::class)->handle(
        campaign: $source,
        data: new UpdatePromoCampaignData(
            name: 'Broncampagne',
            locale: 'fr',
            letterBodyHtml: '<p>Brief {{name}}</p>',
            emailSubject: 'Onderwerp {{name}}',
            emailBodyHtml: '<p>Email {{name}}</p>',
            attachLetterToEmail: false,
            flowImagePath: 'public/images/promo/flow_fr.jpg',
            columnMapping: [
                'name' => 'nom',
                'email' => 'email_general',
            ],
        ),
        actorUserId: (int) $superuser->id,
    );

    $import = PromoCampaignImport::query()->create([
        'promo_campaign_id' => $source->id,
        'original_filename' => 'bron.xlsx',
        'row_count' => 1,
        'imported_by' => $superuser->id,
        'imported_at' => now(),
    ]);

    PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $source->id,
        'promo_campaign_import_id' => $import->id,
        'name' => 'Wavre',
        'email' => 'test@example.com',
    ]);

    Livewire::actingAs($superuser)
        ->test(PromoCampaigns::class)
        ->call('openCopyModal', $source->id)
        ->assertSet('showCopyModal', true)
        ->assertSet('copyLocale', 'fr')
        ->set('copySlug', 'copy-wave')
        ->set('copyName', 'Kopie campagne')
        ->call('copyCampaign')
        ->assertHasNoErrors()
        ->assertRedirect(route('platform.promo-campaigns.edit', PromoCampaign::query()->where('slug', 'copy-wave')->first()));

    $copy = PromoCampaign::query()->where('slug', 'copy-wave')->first();
    $source = $source->fresh();

    expect($copy)
        ->name->toBe('Kopie campagne')
        ->locale->toBe('fr')
        ->letter_body_html->toBe($source->letter_body_html)
        ->email_subject->toBe($source->email_subject)
        ->email_body_html->toBe($source->email_body_html)
        ->attach_letter_to_email->toBe($source->attach_letter_to_email)
        ->flow_image_path->toBe($source->flow_image_path)
        ->column_mapping->toMatchArray($source->column_mapping);

    expect(PromoCampaignTarget::query()->where('promo_campaign_id', $copy->id)->count())->toBe(0);
});

it('toont verzendstatus per promo-campagne in de lijst', function () {
    $superuser = User::factory()->superuser()->create();
    [$campaign, $target] = promoCampaignReadyForEmail($superuser, 'half@example.com');

    PromoCampaignEmailSend::query()->create([
        'promo_campaign_id' => $campaign->id,
        'promo_campaign_target_id' => $target->id,
        'recipient_email' => 'half@example.com',
        'status' => MunicipalPromoEmailSendStatus::Sent,
        'sent_at' => now(),
        'created_by' => $superuser->id,
    ]);

    $import = $campaign->imports()->firstOrFail();
    PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $campaign->id,
        'promo_campaign_import_id' => $import->id,
        'name' => 'Nog te sturen',
        'email' => 'rest@example.com',
        'street_address' => 'Straat 2',
        'postal_code' => '2000',
        'city' => 'Antwerpen',
        'docx_filename' => $target->docx_filename,
        'generated_at' => now(),
    ]);

    $summary = app(\App\Actions\Marketing\SummarizePromoCampaignsDeliveryAction::class)
        ->handle(collect([$campaign->fresh()]))[$campaign->id];

    expect($summary->status)->toBe('needs_restart')
        ->and($summary->sent)->toBe(1)
        ->and($summary->remaining)->toBe(1);

    Livewire::actingAs($superuser)
        ->test(PromoCampaigns::class)
        ->assertSee(__('platform.promo_campaigns.delivery_status.needs_restart'))
        ->assertSee(__('platform.promo_campaigns.delivery_restart_hint'));
});

it('telt database-queue jobs per promo-campagne in de verzendstatus', function () {
    config(['queue.default' => 'database']);

    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmail($superuser, 'queue-count@example.com');

    SendPromoCampaignEmailJob::dispatch(
        promoCampaignId: (int) $campaign->id,
        promoCampaignTargetId: 999,
        actorUserId: (int) $superuser->id,
    )->delay(now()->addHour());

    $summary = app(\App\Actions\Marketing\SummarizePromoCampaignsDeliveryAction::class)
        ->handle(collect([$campaign->fresh()]))[$campaign->id];

    expect($summary->queuedJobs)->toBe(1)
        ->and($summary->status)->toBe('sending');

    \Illuminate\Support\Facades\DB::table('jobs')->where('payload', 'like', '%SendPromoCampaignEmailJob%')->delete();
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
            attachLetterToEmail: true,
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
        ->toBe('https://winprox.app/fr/promo?ref=prm_4cfe5ddb16702059');
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

    $this->get(route('promo', ['locale' => 'nl', 'ref' => $recipient->token]))
        ->assertRedirect(route('promo', ['locale' => 'fr', 'ref' => $recipient->token]));

    $this->followingRedirects()
        ->get(route('promo', ['locale' => 'nl', 'ref' => $recipient->token]))
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
            attachLetterToEmail: true,
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

it('plant opeenvolgende bulk-mails met minstens het ingestelde delay-interval', function () {
    Queue::fake();
    config(['winprox.promo_campaign_email_min_interval_seconds' => 20]);

    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmail($superuser, 'eerste@example.com');

    $import = $campaign->imports()->firstOrFail();
    $firstTarget = $campaign->targets()->firstOrFail();
    $campaign->targets()->create([
        'promo_campaign_import_id' => $import->id,
        'promo_recipient_id' => $firstTarget->promo_recipient_id,
        'name' => 'Tweede gemeente',
        'email' => 'tweede@example.com',
        'street_address' => 'Straat 2',
        'postal_code' => '2000',
        'city' => 'Antwerpen',
        'docx_filename' => $firstTarget->docx_filename,
        'generated_at' => now(),
    ]);

    app(QueuePromoCampaignEmailsAction::class)->handle(
        campaign: $campaign->fresh(),
        actorUserId: (int) $superuser->id,
        delaySeconds: 16,
    );

    $delaySeconds = Queue::pushed(SendPromoCampaignEmailJob::class)
        ->map(function (SendPromoCampaignEmailJob $job): int {
            if ($job->delay === null) {
                return 0;
            }

            return (int) now()->diffInSeconds($job->delay, false);
        })
        ->sort()
        ->values()
        ->all();

    // First job immediate, second forced to >= min interval (20) even if UI asked 16.
    expect($delaySeconds)->toHaveCount(2)
        ->and($delaySeconds[0])->toBeLessThanOrEqual(1)
        ->and($delaySeconds[1])->toBeGreaterThanOrEqual(19);
});

it('laat promo-smtp throttle slechts één send-slot per interval toe', function () {
    config(['winprox.promo_campaign_email_min_interval_seconds' => 20]);
    \Illuminate\Support\Facades\RateLimiter::clear(\App\Support\Marketing\PromoSmtpThrottle::cacheKey());

    expect(\App\Support\Marketing\PromoSmtpThrottle::tryAcquire())->toBeTrue()
        ->and(\App\Support\Marketing\PromoSmtpThrottle::tryAcquire())->toBeFalse()
        ->and(\App\Support\Marketing\PromoSmtpThrottle::secondsUntilAvailable())->toBeGreaterThan(0);
});

it('slaat uitgeschreven adressen over bij bulk promo-campagne mails', function () {
    Queue::fake();
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign, $target] = promoCampaignReadyForEmail($superuser, 'unsub@example.com');

    EmailUnsubscribe::query()->create([
        'email' => 'unsub@example.com',
        'unsubscribed_at' => now(),
    ]);

    $preview = app(QueuePromoCampaignEmailsAction::class)->preview($campaign);
    expect($preview['queued'])->toBe(0)
        ->and($preview['skipped'])->toBe(1);

    $result = app(QueuePromoCampaignEmailsAction::class)->handle(
        campaign: $campaign,
        actorUserId: (int) $superuser->id,
        delaySeconds: 0,
    );

    expect($result['queued'])->toBe(0)
        ->and($result['skipped'])->toBe(1);

    Queue::assertNothingPushed();

    $send = PromoCampaignEmailSend::query()
        ->where('promo_campaign_target_id', $target->id)
        ->first();

    expect($send)->not->toBeNull()
        ->and($send->status)->toBe(MunicipalPromoEmailSendStatus::Skipped)
        ->and($send->error_message)->toBe('unsubscribed');

    $sendResult = app(SendPromoCampaignEmailAction::class)->handle(
        campaign: $campaign->fresh(),
        target: $target->fresh(),
        actorUserId: (int) $superuser->id,
    );

    expect($sendResult?->status)->toBe(MunicipalPromoEmailSendStatus::Skipped);
    Mail::assertNothingSent();
});

it('zet gebouncete promo-adressen op unsubscribe en verwijdert ze uit de campagne', function () {
    Queue::fake();
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign, $target] = promoCampaignReadyForEmail($superuser, 'bounce@example.com');
    $targetId = $target->id;

    PromoCampaignEmailSend::query()->create([
        'promo_campaign_id' => $campaign->id,
        'promo_campaign_target_id' => $target->id,
        'recipient_email' => 'bounce@example.com',
        'status' => MunicipalPromoEmailSendStatus::Sent,
        'sent_at' => now(),
        'created_by' => $superuser->id,
    ]);

    $result = app(\App\Actions\Marketing\MarkPromoCampaignEmailBouncedAction::class)
        ->handle('bounce@example.com', 'Undelivered Mail Returned to Sender');

    expect($result['removed'])->toBe(1)
        ->and($result['blocked'])->toBeTrue()
        ->and(EmailUnsubscribe::isUnsubscribed('bounce@example.com'))->toBeTrue()
        ->and(EmailUnsubscribe::query()->where('email', 'bounce@example.com')->value('source'))
        ->toBe(\App\Enums\EmailUnsubscribeSource::Undeliverable)
        ->and(PromoCampaignTarget::query()->find($targetId))->toBeNull()
        ->and(PromoCampaignEmailSend::query()->where('promo_campaign_id', $campaign->id)->count())->toBe(0);

    $preview = app(QueuePromoCampaignEmailsAction::class)->preview($campaign->fresh(), forceResend: true);
    expect($preview['queued'])->toBe(0);

    Queue::assertNothingPushed();
});

it('blokkeert geen Message-ID als bounce-ontvanger', function () {
    $result = app(\App\Actions\Marketing\MarkPromoCampaignEmailBouncedAction::class)
        ->handle('178430534480.3659332.10843687058335425167@shared200.cloud86-host.io');

    expect($result['removed'])->toBe(0)
        ->and($result['blocked'])->toBeFalse()
        ->and(EmailUnsubscribe::isUnsubscribed(
            '178430534480.3659332.10843687058335425167@shared200.cloud86-host.io',
        ))->toBeFalse();
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

    expect(PromoCampaignEmailSend::query()->where('promo_campaign_id', $campaign->id)->count())->toBe(0);

    $preview = app(QueuePromoCampaignEmailsAction::class)->preview($campaign->fresh());
    expect($preview['queued'])->toBe(1);
});

/**
 * @return array{0: PromoCampaign, 1: PromoCampaignTarget}
 */
function promoCampaignReadyForEmailOnly(User $superuser, string $excelEmail = 'gemeente@example.com'): array
{
    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'email-only-'.uniqid(),
        name: 'Email only',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
    );

    app(UpdatePromoCampaignAction::class)->handle(
        campaign: $campaign,
        data: new UpdatePromoCampaignData(
            name: 'Email only',
            locale: 'nl',
            letterBodyHtml: null,
            emailSubject: 'Test {{name}}',
            emailBodyHtml: '<p>Email {{name}} {{promo_url}}</p>',
            attachLetterToEmail: false,
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

    $target = PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $campaign->id,
        'promo_campaign_import_id' => $import->id,
        'name' => 'Testgemeente',
        'email' => $excelEmail,
        'street_address' => 'Straat 1',
        'postal_code' => '1000',
        'city' => 'Brussel',
    ]);

    return [$campaign->fresh(), $target];
}

it('verstuurt promo-mail zonder bijlage wanneer brief niet vereist is', function () {
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign, $target] = promoCampaignReadyForEmailOnly($superuser);

    app(SendPromoCampaignEmailAction::class)->handle(
        campaign: $campaign,
        target: $target,
        actorUserId: (int) $superuser->id,
        overrideRecipientEmail: 'test@winprox.app',
    );

    Mail::assertSent(PromoCampaignLetterMail::class, function (PromoCampaignLetterMail $mail): bool {
        return $mail->hasTo('test@winprox.app')
            && $mail->docxPath === null
            && $mail->attachments() === [];
    });
});

it('zet bulk-mails in wachtrij zonder brief wanneer bijlage uit staat', function () {
    Queue::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmailOnly($superuser);

    app(QueuePromoCampaignEmailsAction::class)->handle(
        campaign: $campaign,
        actorUserId: (int) $superuser->id,
        delaySeconds: 0,
    );

    Queue::assertPushed(SendPromoCampaignEmailJob::class);
});

it('stuurt testmail zonder brief wanneer bijlage uit staat', function () {
    Mail::fake();

    $superuser = User::factory()->superuser()->create();
    [$campaign] = promoCampaignReadyForEmailOnly($superuser);

    Livewire::actingAs($superuser)
        ->test(PromoCampaignEdit::class, ['promoCampaign' => $campaign])
        ->set('testEmailTo', 'test@winprox.app')
        ->call('sendTestEmail')
        ->assertSet('noticeType', 'success');

    Mail::assertSent(PromoCampaignLetterMail::class, function (PromoCampaignLetterMail $mail): bool {
        return $mail->hasTo('test@winprox.app') && $mail->docxPath === null;
    });
});

it('laat superuser bounces verwerken vanaf de promo-campagnes pagina', function () {
    $superuser = User::factory()->superuser()->create();

    $this->mock(\App\Actions\Marketing\ProcessPromoMailboxBouncesAction::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->once()
            ->with(true, null, false)
            ->andReturn([
                'scanned' => 3,
                'bounce_messages' => 1,
                'emails_found' => 1,
                'removed' => 1,
                'blocked' => 1,
                'dry_run' => false,
            ]);
    });

    Livewire::actingAs($superuser)
        ->test(PromoCampaigns::class)
        ->assertSee(__('platform.promo_campaigns.bounces_submit'))
        ->call('processPromoBounces')
        ->assertSet('flashType', 'success')
        ->assertSet('flashMessage', __('platform.promo_campaigns.bounces_processed', [
            'scanned' => 3,
            'bounces' => 1,
            'emails' => 1,
            'removed' => 1,
            'blocked' => 1,
        ]));
});

it('toont fout wanneer bounce-scan mislukt vanaf promo-campagnes pagina', function () {
    $superuser = User::factory()->superuser()->create();

    $this->mock(\App\Actions\Marketing\ProcessPromoMailboxBouncesAction::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Promo IMAP is not configured (imap.promo).'));
    });

    Livewire::actingAs($superuser)
        ->test(PromoCampaigns::class)
        ->call('processPromoBounces')
        ->assertSet('flashType', 'error')
        ->assertSet('flashMessage', __('platform.promo_campaigns.bounces_failed', [
            'error' => 'Promo IMAP is not configured (imap.promo).',
        ]));
});

it('toont klikstatistieken op campagnepagina', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00', config('app.timezone')));

    $superuser = User::factory()->superuser()->create();

    $campaign = app(CreatePromoCampaignAction::class)->handle(
        slug: 'visit-stats-ui',
        name: 'Visit stats UI',
        locale: 'nl',
        actorUserId: (int) $superuser->id,
    );

    $target = PromoCampaignTarget::query()->create([
        'promo_campaign_id' => $campaign->id,
        'name' => 'Gemeente Test',
        'email' => 'test@example.com',
    ]);

    $recipient = app(CreatePromoRecipientAction::class)->handle(
        label: 'Gemeente Test',
        note: null,
        actorUserId: (int) $superuser->id,
    );

    $target->update(['promo_recipient_id' => $recipient->id]);

    $record = app(RecordPromoVisitAction::class);
    $record->handle($recipient->id, 'nl', now(), PromoVisitPage::Welcome);
    $record->handle($recipient->id, 'nl', now()->addMinutes(5), PromoVisitPage::Promo);

    Livewire::actingAs($superuser)
        ->test(PromoCampaignEdit::class, ['promoCampaign' => $campaign->fresh()])
        ->assertSee(__('platform.promo_campaigns.visit_stats_title'))
        ->assertSee(__('platform.promo_campaigns.visit_stats_totals', [
            'welcome' => 1,
            'promo' => 1,
            'with_visits' => 1,
        ]))
        ->assertSee(__('platform.promo_campaigns.target_visits', [
            'welcome' => 1,
            'promo' => 1,
        ]));

    Carbon::setTestNow();
});
