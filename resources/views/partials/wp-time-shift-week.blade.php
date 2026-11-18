@if ($grid === null)
    <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('time.shifts.week.pick_worker') }}</p></div>
@else
    @php
        $weekLabel = $grid['isCurrentWeek']
            ? __('time.schedule.week_current', ['number' => $grid['weekNumber']])
            : __('time.schedule.week_numbered', ['number' => $grid['weekNumber']]);
    @endphp
    <div class="wp-card wp-time-week" wire:key="shift-week-{{ $grid['weekStart'] }}">
        <div class="wp-time-week__bar">
            <nav class="wp-pagination" aria-label="{{ __('time.schedule.week') }}">
                <div class="wp-pagination__pages">
                    <button type="button" class="wp-pagination__control" wire:click="previousWeek" aria-label="{{ __('time.schedule.prev_week') }}"><x-wp-icon name="chevron-left" class="wp-icon wp-icon--sm" /></button>
                    <button type="button" class="wp-pagination__page wp-pagination__page--action is-active" wire:click="thisWeek" aria-label="{{ __('time.schedule.this_week') }}">{{ $weekLabel }}</button>
                    <button type="button" class="wp-pagination__control" wire:click="nextWeek" aria-label="{{ __('time.schedule.next_week') }}"><x-wp-icon name="chevron-right" class="wp-icon wp-icon--sm" /></button>
                </div>
            </nav>
            @unless ($grid['isCurrentWeek'])
                <button type="button" class="btn btn--sm btn--surface" wire:click="thisWeek">{{ __('time.schedule.today') }}</button>
            @endunless
            <p class="wp-time-week__total wp-tabular">{{ __('time.shifts.week.week_total', ['duration' => \App\Support\Time\WorkDurationFormatter::format($grid['workedMinutes'])]) }}</p>
        </div>

        <div class="wp-time-week__scroll" x-data x-init="$el.scrollTop = ($el.querySelector('[data-hour=\'{{ $grid['firstHour'] }}\']')?.offsetTop ?? 0) - ($el.querySelector('.wp-time-week__head')?.offsetHeight ?? 0)">
            <div class="wp-time-week__grid">
                <div class="wp-time-week__corner"></div>
                @foreach ($grid['days'] as $day)
                    @php
                        $date = \Carbon\Carbon::parse($day['date']);
                    @endphp
                    <div @class(['wp-time-week__head', 'is-today' => $day['isToday']])>
                        <span class="wp-time-week__weekday">{{ $date->translatedFormat('D') }} {{ $date->format('d-m') }}</span>
                        <span class="wp-time-week__day-total wp-tabular">{{ $day['workedMinutes'] > 0 ? \App\Support\Time\WorkDurationFormatter::format($day['workedMinutes']) : '–' }}</span>
                        @foreach ($day['allDayPlanned'] as $label)
                            <span class="wp-time-week__all-day">{{ $label }}</span>
                        @endforeach
                    </div>
                @endforeach

                <div class="wp-time-week__hours" aria-hidden="true">
                    @for ($hour = 0; $hour < 24; $hour++)
                        <span class="wp-time-week__hour" data-hour="{{ $hour }}">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}</span>
                    @endfor
                </div>
                @foreach ($grid['days'] as $day)
                    <div @class(['wp-time-week__day', 'is-today' => $day['isToday']])>
                        @foreach ($day['planned'] as $planned)
                            <div class="wp-time-week__planned" style="--wp-tw-top: {{ $planned['topPct'] }}%; --wp-tw-height: {{ $planned['heightPct'] }}%;" title="{{ __('time.shifts.planned_label') }}: {{ $planned['label'] }}">
                                <span class="wp-time-week__planned-label">{{ $planned['label'] }}</span>
                            </div>
                        @endforeach
                        @foreach ($day['blocks'] as $block)
                            @php
                                $range = $block['start'].' – '.($block['end'] ?? __('time.shifts.week.now'));
                                $canCorrect = ! $block['open'] && (auth()->user()?->can('correct', $block['shift']) ?? false);
                                $clickable = $block['open'] || $canCorrect;
                            @endphp
                            <button
                                type="button"
                                @class(['wp-time-week__block', 'is-open' => $block['open']])
                                style="--wp-tw-top: {{ $block['topPct'] }}%; --wp-tw-height: {{ $block['heightPct'] }}%;"
                                @if ($block['open']) wire:click="openForceClose({{ $block['shiftId'] }})" @elseif ($canCorrect) wire:click="openCorrection({{ $block['shiftId'] }})" @endif
                                @disabled(! $clickable)
                                title="{{ $range }}{{ $block['place'] ? ' · '.$block['place'] : '' }}"
                            >
                                @foreach ($block['breaks'] as $break)
                                    <span class="wp-time-week__break" style="--wp-tw-top: {{ $break['topPct'] }}%; --wp-tw-height: {{ $break['heightPct'] }}%;" aria-hidden="true"></span>
                                @endforeach
                                <span class="wp-time-week__block-time wp-tabular">{{ $range }}</span>
                                @if ($block['place'])
                                    <span class="wp-time-week__block-place">{{ $block['place'] }}</span>
                                @endif
                                <span class="wp-time-week__block-worked wp-tabular">{{ \App\Support\Time\WorkDurationFormatter::format($block['workedMinutes']) }}</span>
                            </button>
                        @endforeach
                        @if ($day['nowPct'] !== null)
                            <span class="wp-time-week__now" style="--wp-tw-top: {{ $day['nowPct'] }}%;" aria-hidden="true"></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
