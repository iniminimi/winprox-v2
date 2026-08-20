<div class="wp-stack" data-wp-promo-campaign-edit>
    <x-wp-page-head-title
        icon="document"
        :title="$campaign->name"
        :subtitle="$campaign->slug"
    />

    <p>
        <a href="{{ route('platform.promo-campaigns') }}" class="btn btn--ghost btn--sm" wire:navigate>
            {{ __('platform.promo_campaigns.back') }}
        </a>
    </p>

    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.delivery_stats_title') }}</p>
        <p class="wp-muted wp-text-sm">
            {{ __('platform.promo_campaigns.stats', $stats) }}
        </p>
    </div>

    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.visit_stats_title') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.visit_stats_lead') }}</p>
        @if ($visitStats->welcome > 0 || $visitStats->promo > 0 || $visitStats->engaged > 0 || $visitStats->follow > 0)
            <p class="wp-text-body">
                {{ __('platform.promo_campaigns.visit_stats_totals', [
                    'welcome' => $visitStats->welcome,
                    'promo' => $visitStats->promo,
                    'with_visits' => $visitStats->targetsWithVisits,
                ]) }}
            </p>
            <p class="wp-text-body">
                {{ __('platform.promo_campaigns.visit_stats_real', [
                    'engaged' => $visitStats->engaged,
                    'returning' => $visitStats->returning,
                    'follow' => $visitStats->follow,
                ]) }}
            </p>
        @else
            <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.visit_stats_empty') }}</p>
        @endif
    </div>

    <form wire:submit="save" class="wp-stack">
        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.settings_title') }}</p>
            <div class="wp-promo-form-grid">
                <div class="wp-promo-form-grid__pair">
                    <div>
                        <label class="wp-label" for="edit-name">{{ __('platform.promo_campaigns.name') }}</label>
                        <input id="edit-name" type="text" class="wp-input" wire:model="name">
                        @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-promo-form-grid__locale">
                        <label class="wp-label" for="edit-locale">{{ __('platform.promo_campaigns.locale') }}</label>
                        <select id="edit-locale" class="wp-select" wire:model="locale">
                            @foreach (config('locales.supported', []) as $localeCode)
                                <option value="{{ $localeCode }}">{{ config('locales.labels.'.$localeCode, strtoupper($localeCode)) }}</option>
                            @endforeach
                        </select>
                        @error('locale') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="wp-label" for="edit-flow">{{ __('platform.promo_campaigns.flow_image') }}</label>
                    <select id="edit-flow" class="wp-input" wire:model="flowImagePath">
                        <option value="">{{ __('platform.promo_campaigns.flow_none') }}</option>
                        @foreach ($flowImages as $imagePath)
                            <option value="{{ $imagePath }}">{{ basename($imagePath) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="wp-label" for="edit-youtube">{{ __('platform.promo_campaigns.youtube_url') }}</label>
                    <input id="edit-youtube" type="url" class="wp-input" wire:model="youtubeUrl" placeholder="https://www.youtube.com/watch?v=...">
                    <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.youtube_url_hint') }}</p>
                    @error('youtubeUrl') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.letter_title') }}</p>
            <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.letter_lead') }}</p>
            <label class="wp-cluster wp-text-sm">
                <input type="checkbox" wire:model="attachLetterToEmail">
                {{ __('platform.promo_campaigns.attach_letter_to_email') }}
            </label>
            <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.attach_letter_to_email_hint') }}</p>
            <p class="wp-muted wp-text-sm">@lang('platform.promo_campaigns.placeholders_hint')</p>
            <div
                wire:ignore
                class="wp-promo-quill wp-promo-quill--tall"
                data-wp-promo-quill
                data-textarea-id="letter-body-html"
            >
                <div class="wp-promo-quill-editor"></div>
            </div>
            <textarea id="letter-body-html" class="sr-only" wire:model="letterBodyHtml"></textarea>
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.email_title') }}</p>
            <p class="wp-muted wp-text-sm">@lang('platform.promo_campaigns.email_placeholders_hint')</p>
            <div class="wp-flash wp-flash--muted">
                <p class="wp-text-body">@lang('platform.promo_campaigns.youtube_thumbnail_how_to_title')</p>
                <p class="wp-muted wp-text-sm">@lang('platform.promo_campaigns.youtube_thumbnail_how_to')</p>
            </div>
            <div class="wp-flash wp-flash--muted">
                <p class="wp-text-body">@lang('platform.promo_campaigns.welcome_url_how_to_title')</p>
                <p class="wp-muted wp-text-sm">@lang('platform.promo_campaigns.welcome_url_how_to')</p>
            </div>
            <div>
                <label class="wp-label" for="email-subject">{{ __('platform.promo_campaigns.email_subject') }}</label>
                <input id="email-subject" type="text" class="wp-input" wire:model="emailSubject">
                @error('emailSubject') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div
                wire:ignore
                class="wp-promo-quill"
                data-wp-promo-quill
                data-wp-promo-toolbar="email"
                data-textarea-id="email-body-html"
            >
                <div class="wp-promo-quill-editor"></div>
            </div>
            <textarea id="email-body-html" class="sr-only" wire:model="emailBodyHtml"></textarea>
        </div>

        <button type="submit" class="btn btn--primary">{{ __('platform.promo_campaigns.save') }}</button>
    </form>

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.import_title') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.import_lead') }}</p>

        @if ($latestImport)
            <div class="wp-flash wp-flash--muted">
                {{ __('platform.promo_campaigns.last_import', [
                    'count' => $latestImport->row_count,
                    'filename' => $latestImport->original_filename,
                    'datetime' => $latestImport->imported_at?->format('d-m-Y H:i'),
                ]) }}
            </div>
        @endif

        <form wire:submit="importSpreadsheet" class="wp-stack-tight">
            <div>
                <label class="wp-label" for="spreadsheet">{{ __('platform.promo_campaigns.spreadsheet') }}</label>
                <input id="spreadsheet" type="file" class="wp-input" wire:model="spreadsheet" accept=".xlsx">
                @error('spreadsheet') <p class="wp-error">{{ $message }}</p> @enderror
            </div>

            @if ($detectedHeaders !== [])
                <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.detected_headers') }}: {{ implode(', ', $detectedHeaders) }}</p>
            @endif

            <div class="wp-row wp-gap-md wp-wrap">
                <div>
                    <label class="wp-label">{{ __('platform.promo_campaigns.map_name') }}</label>
                    <select class="wp-input" wire:model="mapName">
                        <option value="">{{ __('platform.promo_campaigns.map_select') }}</option>
                        @foreach ($detectedHeaders as $header)
                            <option value="{{ $header }}">{{ $header }}</option>
                        @endforeach
                    </select>
                    @error('mapName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="wp-label">{{ __('platform.promo_campaigns.map_email') }}</label>
                    <select class="wp-input" wire:model="mapEmail">
                        <option value="">{{ __('platform.promo_campaigns.map_select') }}</option>
                        @foreach ($detectedHeaders as $header)
                            <option value="{{ $header }}">{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="wp-label">{{ __('platform.promo_campaigns.map_street') }}</label>
                    <select class="wp-input" wire:model="mapStreetAddress">
                        <option value="">{{ __('platform.promo_campaigns.map_select') }}</option>
                        @foreach ($detectedHeaders as $header)
                            <option value="{{ $header }}">{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="wp-label">{{ __('platform.promo_campaigns.map_postal') }}</label>
                    <select class="wp-input" wire:model="mapPostalCode">
                        <option value="">{{ __('platform.promo_campaigns.map_select') }}</option>
                        @foreach ($detectedHeaders as $header)
                            <option value="{{ $header }}">{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="wp-label">{{ __('platform.promo_campaigns.map_city') }}</label>
                    <select class="wp-input" wire:model="mapCity">
                        <option value="">{{ __('platform.promo_campaigns.map_select') }}</option>
                        @foreach ($detectedHeaders as $header)
                            <option value="{{ $header }}">{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="importSpreadsheet,spreadsheet">
                <x-wp-spinner wire:loading wire:target="importSpreadsheet,spreadsheet" class="wp-mr-2" />
                <span wire:loading.remove wire:target="importSpreadsheet">{{ __('platform.promo_campaigns.import_submit') }}</span>
                <span wire:loading wire:target="importSpreadsheet">{{ __('platform.promo_campaigns.import_loading') }}</span>
            </button>
        </form>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.actions_title') }}</p>

        <div class="wp-row wp-gap-sm wp-wrap wp-items-center">
            <label class="wp-row wp-gap-xs wp-text-sm">
                <input type="checkbox" wire:model="forceGenerate">
                {{ __('platform.promo_campaigns.force_generate') }}
            </label>
            <button type="button" class="btn btn--primary btn--sm" wire:click="generateLetters" wire:loading.attr="disabled" wire:target="generateLetters">
                <x-wp-hourglass wire:loading wire:target="generateLetters" size="sm" class="wp-mr-2" />
                <span wire:loading.remove wire:target="generateLetters">{{ __('platform.promo_campaigns.generate') }}</span>
                <span wire:loading wire:target="generateLetters">{{ __('platform.promo_campaigns.generate_loading') }}</span>
            </button>
        </div>

        <div class="wp-border-top wp-stack-tight">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.test_email_section') }}</p>
            <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.test_email_lead') }}</p>
            <div class="wp-row wp-gap-md wp-wrap wp-items-end">
                <div class="wp-promo-actions__field">
                    <label class="wp-label" for="test-email-to">{{ __('platform.promo_campaigns.test_email_to') }}</label>
                    <input id="test-email-to" type="email" class="wp-input" wire:model="testEmailTo" autocomplete="email">
                    <p class="wp-muted wp-text-sm wp-mt-1">{{ __('platform.promo_campaigns.test_email_to_hint') }}</p>
                </div>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="sendTestEmail">
                    {{ __('platform.promo_campaigns.test_email') }}
                </button>
            </div>
        </div>

        <div class="wp-border-top wp-stack-tight">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.queue_section') }}</p>
            <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.queue_lead') }}</p>
            <div class="wp-row wp-gap-md wp-wrap wp-items-end">
                <div>
                    <label class="wp-label" for="delay-seconds">{{ __('platform.promo_campaigns.delay_seconds') }}</label>
                    <input id="delay-seconds" type="number" min="20" class="wp-input wp-promo-actions__delay-input" wire:model="delaySeconds">
                    <p class="wp-muted wp-text-sm wp-mt-1">{{ __('platform.promo_campaigns.delay_seconds_hint') }}</p>
                </div>
                <label class="wp-row wp-gap-xs wp-text-sm wp-promo-actions__checkbox">
                    <input type="checkbox" wire:model="forceSend">
                    {{ __('platform.promo_campaigns.force_send') }}
                </label>
                <button type="button" class="btn btn--primary btn--sm" wire:click="openQueueConfirm">
                    {{ __('platform.promo_campaigns.queue_emails') }}
                </button>
            </div>
            <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.queue_hint') }}</p>
        </div>
    </div>

    @if ($targets->isNotEmpty())
        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.targets_title') }}</p>
            <div class="wp-list wp-list--entity-rows">
                @foreach ($targets as $target)
                    <div class="wp-list-row" wire:key="target-{{ $target->id }}">
                        <div class="wp-grow">
                            <p class="wp-text-body">{{ $target->name }}</p>
                            <p class="wp-muted wp-text-sm">
                                {{ $target->email ?? '—' }}
                                ·
                                @if ($target->generated_at)
                                    {{ __('platform.promo_campaigns.letter_ready') }}
                                @else
                                    {{ __('platform.promo_campaigns.letter_missing') }}
                                @endif
                                ·
                                @if ($target->undelivered)
                                    {{ __('platform.promo_campaigns.email_bounced') }}
                                @elseif ($target->latestSentEmailSend)
                                    @php
                                        $sentTo = trim((string) ($target->latestSentEmailSend->recipient_email ?? ''));
                                        $excelEmail = trim((string) ($target->email ?? ''));
                                    @endphp
                                    @if ($sentTo !== '' && strcasecmp($sentTo, $excelEmail) !== 0)
                                        {{ __('platform.promo_campaigns.email_sent_to', [
                                            'date' => $target->latestSentEmailSend->sent_at?->format('d-m-Y H:i'),
                                            'email' => $sentTo,
                                        ]) }}
                                    @else
                                        {{ __('platform.promo_campaigns.email_sent', ['date' => $target->latestSentEmailSend->sent_at?->format('d-m-Y H:i')]) }}
                                    @endif
                                @elseif ($target->latestEmailSend?->status?->value === 'bounced')
                                    {{ __('platform.promo_campaigns.email_bounced') }}
                                @elseif ($target->latestEmailSend?->status?->value === 'skipped'
                                    && $target->latestEmailSend?->error_message === 'unsubscribed')
                                    {{ __('platform.promo_campaigns.email_unsubscribed') }}
                                @elseif ($target->latestEmailSend?->status?->value === 'failed')
                                    {{ __('platform.promo_campaigns.email_failed') }}
                                @else
                                    {{ __('platform.promo_campaigns.email_not_sent') }}
                                @endif
                            </p>
                            @php
                                $targetVisits = $visitStats->forTarget((int) $target->id);
                            @endphp
                            <p class="wp-muted wp-text-sm">
                                @if ($targetVisits['welcome'] > 0 || $targetVisits['promo'] > 0 || $targetVisits['engaged'] > 0 || $targetVisits['follow'] > 0)
                                    {{ __('platform.promo_campaigns.target_visits', [
                                        'welcome' => $targetVisits['welcome'],
                                        'promo' => $targetVisits['promo'],
                                    ]) }}
                                    ·
                                    {{ __('platform.promo_campaigns.target_visits_real', [
                                        'engaged' => $targetVisits['engaged'],
                                        'follow' => $targetVisits['follow'],
                                    ]) }}
                                    @if ($targetVisits['returning'])
                                        · {{ __('platform.promo_campaigns.target_visits_returning') }}
                                    @endif
                                @else
                                    {{ __('platform.promo_campaigns.target_visits_none') }}
                                @endif
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
            @if ($stats['targets'] > $targets->count())
                <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.targets_truncated', ['shown' => $targets->count(), 'total' => $stats['targets']]) }}</p>
            @endif
        </div>
    @endif

    @if ($showQueueConfirm)
        <x-wp-modal closeMethod="dismissQueueConfirm">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card" role="alertdialog" aria-labelledby="promo-campaign-queue-confirm-title">
                <div class="wp-modal-head">
                    <h2 id="promo-campaign-queue-confirm-title" class="wp-section-title">{{ __('platform.promo_campaigns.queue_confirm_title') }}</h2>
                    <x-wp-modal-close wire:click="dismissQueueConfirm" />
                </div>
                <div class="wp-modal-body wp-stack-tight">
                    <p class="wp-text-body">{{ __('platform.promo_campaigns.queue_confirm_body', ['count' => $queueConfirmQueued]) }}</p>
                    @if ($queueConfirmSkipped > 0)
                        <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.queue_confirm_skipped', ['skipped' => $queueConfirmSkipped]) }}</p>
                    @endif
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="dismissQueueConfirm">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="confirmQueueEmails">{{ __('platform.promo_campaigns.queue_confirm_submit') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($noticeMessage)
        <x-wp-modal closeMethod="dismissNotice">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card" role="alertdialog" aria-labelledby="promo-campaign-notice-title">
                <div class="wp-modal-head">
                    <h2 id="promo-campaign-notice-title" class="wp-section-title">{{ __('platform.promo_campaigns.notice_title') }}</h2>
                    <x-wp-modal-close wire:click="dismissNotice" />
                </div>
                <div class="wp-modal-body">
                    <p @class([
                        'wp-text-body',
                        'wp-text-danger' => $noticeType === 'error',
                    ])>{{ $noticeMessage }}</p>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--primary" wire:click="dismissNotice">{{ __('common.button.close') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
