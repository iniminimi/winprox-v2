<?php

namespace App\Actions\Public;

use App\Actions\Issues\CreateIssueAction;
use App\Models\Issue;
use App\Models\Unit;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;

/**
 * Publieke QR-melding: maakt een (niet-goedgekeurde) melding voor een unit, maakt
 * automatisch een taak voor het standaardteam van de unit (port van FacilityQrIntake)
 * en bewaart de meegestuurde, reeds gecomprimeerde foto's. Geen (actief) team =>
 * geen taak, dus de melding blijft "Nieuw". Altijd ongekeurd (moderatie, §7.1).
 */
class SubmitReportAction
{
    public function __construct(
        private CreateIssueAction $createIssue,
        private IssuePhotoStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Unit $unit, array $data, array $photos = []): Issue
    {
        $unit->loadMissing('defaultInternalTeam');
        $team = $unit->defaultInternalTeam;
        $teamIds = ($team !== null && $team->is_active) ? [$team->id] : [];

        $issue = $this->createIssue->handle([
            'location_id' => $unit->location_id,
            'unit_id' => $unit->id,
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_contact' => $data['reporter_contact'] ?? null,
            'description' => $data['description'],
        ], $teamIds);

        foreach ($photos as $photo) {
            if ($photo instanceof UploadedFile) {
                $issue->photos()->create([
                    'path' => $this->storage->storePrecompressedCopy($photo),
                ]);
            }
        }

        return $issue;
    }
}
