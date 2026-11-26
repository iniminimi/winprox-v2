<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->comments() as $table => $comment) {
            $this->setComment($table, $comment);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->comments()) as $table) {
            $this->setComment($table, '');
        }
    }

    private function setComment(string $table, string $comment): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $table) || ! Schema::hasTable($table)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` COMMENT %s',
            $table,
            DB::getPdo()->quote($comment),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function comments(): array
    {
        return [
            'absence_request_photos' => 'Foto’s bij een afwezigheidsaanvraag.',
            'absence_requests' => 'Aanvragen voor verlof, recup of ziekte.',
            'announcement_translations' => 'Vertaling van een mededeling per taal.',
            'announcements' => 'Mededelingen op een locatie of unit.',
            'audit_logs' => 'Log van wie wat wijzigde.',
            'belgian_public_holidays' => 'Belgische feestdagen van 2020 tot 2040, zonder organisatie.',
            'cache' => 'Tijdelijke Laravel-cache.',
            'cache_locks' => 'Vergrendelingen van de Laravel-cache.',
            'camera_claims' => 'Koppelcode om een camera aan een organisatie te hangen.',
            'camera_photos' => 'Foto’s die een camera binnenstuurde.',
            'cameras' => 'Gekoppelde camera’s.',
            'categories' => 'Categorieën voor meldingen en taken.',
            'category_internal_team' => 'Welke teams een categorie oppakken.',
            'category_translations' => 'Vertaling van een categorienaam per taal.',
            'clock_display_claims' => 'Koppelcode voor een prikklokscherm.',
            'clock_display_images' => 'Foto’s op het prikklokscherm.',
            'clock_point_qr_tokens' => 'Draaiende QR-codes van een prikklok.',
            'clock_points' => 'Prikklokken op een locatie.',
            'contact_messages' => 'Berichten van het contactformulier op de website.',
            'customers' => 'Klanten, onder andere in Checkmate.',
            'document_translations' => 'Vertaling van een document per taal.',
            'documents' => 'Documenten bij een locatie of unit.',
            'email_action_tokens' => 'Eenmalige links in e-mail, bijvoorbeeld bevestigen of afmelden.',
            'email_unsubscribes' => 'Adressen die geen promo-mail meer willen.',
            'esg_indicator_translations' => 'Vertaling van een ESG-indicator per taal.',
            'esg_indicators' => 'ESG-indicatoren van een organisatie.',
            'esg_measurements' => 'Metingen bij een ESG-indicator.',
            'failed_jobs' => 'Mislukte achtergrondtaken.',
            'field_sync_receipts' => 'Ontvangstbewijs van een offline inzending, zodat die niet dubbel binnenkomt.',
            'geoname_places' => 'Plaatsen voor adresvoorstellen.',
            'hardware_device_events' => 'Gebeurtenissen van een gekoppeld toestel.',
            'hardware_devices' => 'Toestellen in voorraad of bij een organisatie: klok, scherm, camera of sensor.',
            'help_chat_knowledge_base_entries' => 'Artikels voor de hulpchat.',
            'help_chat_knowledge_base_entry_translations' => 'Vertaling van een hulpartikel per taal.',
            'help_chat_unanswered_questions' => 'Vragen die de hulpchat niet kon beantwoorden.',
            'internal_team_translations' => 'Vertaling van een teamnaam per taal.',
            'internal_teams' => 'Teams binnen een organisatie.',
            'iot_events' => 'Meldingen die een IoT-regel uitstuurde.',
            'iot_gateways' => 'IoT-gateways.',
            'iot_rules' => 'Regels die een sensorwaarde omzetten in een melding.',
            'iot_sensors' => 'IoT-sensoren.',
            'issue_photos' => 'Foto’s bij een melding.',
            'issue_round_stops' => 'Haltes van een controleronde.',
            'issue_translations' => 'Vertaling van een melding per taal.',
            'issue_updates' => 'Opvolging en notities bij een melding.',
            'issues' => 'Meldingen.',
            'job_batches' => 'Groepen van achtergrondtaken.',
            'jobs' => 'Wachtrij van achtergrondtaken.',
            'location_translations' => 'Vertaling van een locatienaam per taal.',
            'location_user' => 'Welke backoffice-gebruikers een locatie zien.',
            'location_worker' => 'Welke uitvoerders op een locatie werken.',
            'locations' => 'Locaties van een organisatie.',
            'migrations' => 'Welke databasemigraties al uitgevoerd zijn.',
            'municipal_promo_email_sends' => 'Promo-mails naar gemeenten.',
            'ops_portals' => 'Interne pagina om hardware te koppelen.',
            'password_reset_tokens' => 'Tokens om een wachtwoord te resetten.',
            'personal_access_tokens' => 'API-tokens.',
            'planned_shifts' => 'Geplande diensten en afwezigheden op het rooster.',
            'presence_submissions' => 'CIAO-aanwezigheidsmeldingen naar de RSZ.',
            'promo_campaign_email_sends' => 'Verzonden mails van een promocampagne.',
            'promo_campaign_imports' => 'Importbestanden van een promocampagne.',
            'promo_campaign_targets' => 'Ontvangers van een promocampagne.',
            'promo_campaigns' => 'Promocampagnes.',
            'promo_recipients' => 'Adressen voor promomail.',
            'promo_video_plays' => 'Afspelen van de promovideo.',
            'promo_visits' => 'Bezoeken aan de promosite.',
            'qr_codes' => 'QR-codes van locaties en units.',
            'qr_link_photos' => 'Foto’s die via een QR-code bij een unit horen.',
            'qr_report_email_holds' => 'QR-meldingen die wachten op e-mailbevestiging.',
            'qr_scans' => 'Scans van een QR-code.',
            'reservations' => 'Reserveringen.',
            'roster_restore_points' => 'Bewaarde versie van het rooster om terug te zetten.',
            'screen_claims' => 'Koppelcode voor een klokscherm.',
            'screens' => 'Gekoppelde klokschermen.',
            'sense_points' => 'Sense-meetpunten voor klimaat.',
            'sense_readings' => 'Metingen van een Sense-punt.',
            'sessions' => 'Inlogsessies.',
            'shift_types' => 'Soorten diensten en afwezigheid.',
            'task_round_stop_skips' => 'Overgeslagen halte in een controleronde.',
            'task_translations' => 'Vertaling van een taak per taal.',
            'tasks' => 'Taken.',
            'team_announcements' => 'Mededelingen voor een team.',
            'team_documents' => 'Documenten van een team.',
            'tenant_holiday_agreements' => 'Wat een organisatie met een feestdag doet: laten vallen of vervangen.',
            'tenant_purge_requests' => 'Aanvraag om een organisatie te wissen.',
            'tenant_qr_sticker_sheet_settings' => 'Opmaak van het QR-stickervel per organisatie.',
            'tenant_time_archives' => 'Archiefbestand van Time-gegevens van een organisatie.',
            'tenants' => 'Organisaties.',
            'unit_album_photos' => 'Foto’s in het fotoalbum van een unit.',
            'unit_album_recipients' => 'Ontvangers van een unit-fotoalbum.',
            'unit_album_sends' => 'Verzendingen van een unit-fotoalbum.',
            'unit_bulk_batches' => 'Units die in één keer samen werden aangemaakt.',
            'unit_check_list_items' => 'Punten op een checklist.',
            'unit_check_list_translations' => 'Vertaling van een checklist per taal.',
            'unit_check_lists' => 'Checklists.',
            'unit_check_photos' => 'Foto’s bij een unitcontrole.',
            'unit_checks' => 'Ingevulde unitcontroles.',
            'unit_gps_reports' => 'GPS-positie die bij een unit werd gemeld.',
            'unit_measure_field_unit' => 'Welke meetvelden bij een unit horen.',
            'unit_measure_fields' => 'Definitie van een meetveld.',
            'unit_measurements' => 'Ingevulde metingen van een unit.',
            'unit_portal_visits' => 'Bezoeken aan het QR-portaal van een unit.',
            'unit_translations' => 'Vertaling van een unitnaam per taal.',
            'units' => 'Units binnen een locatie.',
            'users' => 'Backoffice-gebruikers en de platform-superuser.',
            'webhook_deliveries' => 'Pogingen om een webhook af te leveren.',
            'webhook_endpoints' => 'Webhook-adressen van een organisatie.',
            'welcome_visits' => 'Bezoeken aan het welkomstscherm.',
            'work_breaks' => 'Pauzes binnen een geklokte dienst.',
            'work_shift_task_logs' => 'Taken die tijdens een geklokte dienst werden gedaan.',
            'work_shifts' => 'Geklokte diensten: inklok en uitklok.',
            'work_visits' => 'GPS-bezoeken tijdens het werk.',
            'worker_devices' => 'Toestellen waarmee een uitvoerder het portaal opent.',
            'worker_leave_entitlements' => 'Verlofsaldo van een uitvoerder.',
            'worker_notifications' => 'Meldingen voor een uitvoerder in het portaal.',
            'worker_passkey_credentials' => 'Passkeys van een uitvoerder.',
            'worker_weekday_minutes' => 'Vaste werkregeling: normale minuten per weekdag van een uitvoerder.',
            'workers' => 'Uitvoerders.',
        ];
    }
};
