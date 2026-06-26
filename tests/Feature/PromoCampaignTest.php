<?php

use App\Actions\Marketing\CreatePromoCampaignAction;
use App\Actions\Marketing\GeneratePromoCampaignLettersAction;
use App\Actions\Marketing\ImportPromoCampaignSpreadsheetAction;
use App\Actions\Marketing\UpdatePromoCampaignAction;
use App\Data\Marketing\UpdatePromoCampaignData;
use App\Livewire\Platform\PromoCampaignEdit;
use App\Livewire\Platform\PromoCampaigns;
use App\Models\PromoCampaign;
use App\Models\PromoCampaignTarget;
use App\Models\User;
use App\Support\Marketing\PromoCampaignPlaceholderRenderer;
use App\Support\Marketing\PromoCampaignSpreadsheetReader;
use App\Support\Qr\QrCodePngWriter;
use Illuminate\Http\UploadedFile;
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
        ->assertSet('importNoticeType', 'success')
        ->assertSee(__('platform.promo_campaigns.imported_detail', [
            'count' => PromoCampaignTarget::query()->where('promo_campaign_id', $campaign->id)->count(),
            'filename' => 'sample.xlsx',
        ]));
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
            letterBodyHtml: '<p>Bonjour {{name}}</p>',
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

    @unlink($docxPath);
});

it('vervangt placeholders', function () {
    $html = PromoCampaignPlaceholderRenderer::render(
        'Bonjour {{name}} à {{city}}',
        ['name' => 'Amay', 'city' => 'Amay'],
    );

    expect($html)->toBe('Bonjour Amay à Amay');
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
