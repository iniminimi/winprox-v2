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

    @if ($flashMessage)
        <div class="wp-alert wp-alert--{{ $flashType === 'error' ? 'error' : 'success' }}">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-muted wp-text-sm">
            {{ __('platform.promo_campaigns.stats', $stats) }}
        </p>
        <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.placeholders') }}: <code>{{ $placeholders }}</code></p>
    </div>

    <form wire:submit="save" class="wp-stack">
        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.settings_title') }}</p>
            <div class="wp-row wp-gap-md wp-wrap">
                <div class="wp-grow">
                    <label class="wp-label" for="edit-name">{{ __('platform.promo_campaigns.name') }}</label>
                    <input id="edit-name" type="text" class="wp-input" wire:model="name">
                    @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="wp-label" for="edit-locale">{{ __('platform.promo_campaigns.locale') }}</label>
                    <input id="edit-locale" type="text" class="wp-input" wire:model="locale" maxlength="5">
                    @error('locale') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-grow">
                    <label class="wp-label" for="edit-flow">{{ __('platform.promo_campaigns.flow_image') }}</label>
                    <select id="edit-flow" class="wp-input" wire:model="flowImagePath">
                        <option value="">{{ __('platform.promo_campaigns.flow_none') }}</option>
                        @foreach ($flowImages as $imagePath)
                            <option value="{{ $imagePath }}">{{ basename($imagePath) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.letter_title') }}</p>
            <p class="wp-muted wp-text-sm">{{ __('platform.promo_campaigns.letter_lead') }}</p>
            <div
                wire:ignore
                class="wp-promo-quill wp-promo-quill--tall"
                data-wp-promo-quill
                data-textarea-id="letter-body-html"
                data-initial-html="{{ e($letterBodyHtml) }}"
            >
                <div class="wp-promo-quill-editor"></div>
            </div>
            <textarea id="letter-body-html" class="sr-only" wire:model="letterBodyHtml"></textarea>
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            <p class="wp-subhead">{{ __('platform.promo_campaigns.email_title') }}</p>
            <div>
                <label class="wp-label" for="email-subject">{{ __('platform.promo_campaigns.email_subject') }}</label>
                <input id="email-subject" type="text" class="wp-input" wire:model="emailSubject">
                @error('emailSubject') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div
                wire:ignore
                class="wp-promo-quill"
                data-wp-promo-quill
                data-textarea-id="email-body-html"
                data-initial-html="{{ e($emailBodyHtml) }}"
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
                    @error('columnMapping.name') <p class="wp-error">{{ $message }}</p> @enderror
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

            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                {{ __('platform.promo_campaigns.import_submit') }}
            </button>
        </form>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <p class="wp-subhead">{{ __('platform.promo_campaigns.actions_title') }}</p>
        <div class="wp-row wp-gap-sm wp-wrap">
            <label class="wp-row wp-gap-xs wp-text-sm">
                <input type="checkbox" wire:model="forceGenerate">
                {{ __('platform.promo_campaigns.force_generate') }}
            </label>
            <button type="button" class="btn btn--primary btn--sm" wire:click="generateLetters">
                {{ __('platform.promo_campaigns.generate') }}
            </button>
        </div>

        <div class="wp-stack-tight wp-mt-4">
            <div class="wp-row wp-gap-md wp-wrap">
                <div>
                    <label class="wp-label" for="override-to">{{ __('platform.promo_campaigns.override_to') }}</label>
                    <input id="override-to" type="email" class="wp-input" wire:model="overrideTo">
                </div>
                <div>
                    <label class="wp-label" for="delay-seconds">{{ __('platform.promo_campaigns.delay_seconds') }}</label>
                    <input id="delay-seconds" type="number" min="0" class="wp-input" wire:model="delaySeconds">
                </div>
            </div>
            <div class="wp-row wp-gap-sm wp-wrap">
                <button type="button" class="btn btn--ghost btn--sm" wire:click="sendTestEmail">
                    {{ __('platform.promo_campaigns.test_email') }}
                </button>
                <label class="wp-row wp-gap-xs wp-text-sm">
                    <input type="checkbox" wire:model="forceSend">
                    {{ __('platform.promo_campaigns.force_send') }}
                </label>
                <button type="button" class="btn btn--primary btn--sm" wire:click="queueEmails">
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
                                @if ($target->latestSentEmailSend)
                                    {{ __('platform.promo_campaigns.email_sent', ['date' => $target->latestSentEmailSend->sent_at?->format('d-m-Y H:i')]) }}
                                @elseif ($target->latestEmailSend?->status?->value === 'failed')
                                    {{ __('platform.promo_campaigns.email_failed') }}
                                @else
                                    {{ __('platform.promo_campaigns.email_not_sent') }}
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
</div>
