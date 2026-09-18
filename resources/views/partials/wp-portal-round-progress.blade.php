{{-- Rijke voortgang voor inspectieronde (fase 2). --}}
@props(['progress', 'currentUnitId' => null])

@php
    $done = (int) ($progress['done'] ?? 0);
    $total = max(1, (int) ($progress['total'] ?? 1));
    $pct = (int) round(($done / $total) * 100);
@endphp

<div class="wp-round-progress wp-stack-tight">
    <div class="wp-row">
        <p class="wp-muted wp-text-sm wp-grow">
            {{ __('portal.round.progress', ['done' => $done, 'total' => $progress['total']]) }}
        </p>
        @if (! empty($progress['next_unit_name']) && (int) ($progress['open'] ?? 0) > 0)
            <p class="wp-text-sm">{{ __('portal.round.next_stop', ['name' => $progress['next_unit_name']]) }}</p>
        @endif
    </div>

    <div class="wp-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"
         aria-label="{{ __('portal.round.progress', ['done' => $done, 'total' => $progress['total']]) }}">
        <div class="wp-progress__bar" style="width: {{ $pct }}%"></div>
    </div>

    <ol class="wp-round-stops">
        @foreach ($progress['stops'] as $index => $stop)
            @php
                $state = $stop['state'];
                $pill = match ($state) {
                    'ok' => 'done',
                    'not_ok' => 'closed',
                    'skipped' => 'closed',
                    'current' => 'progress',
                    default => 'new',
                };
                $label = match ($state) {
                    'ok' => __('portal.round.stop_ok'),
                    'not_ok' => __('portal.round.stop_not_ok'),
                    'skipped' => __('portal.round.stop_skipped'),
                    'current' => __('portal.round.stop_current'),
                    default => __('portal.round.stop_open'),
                };
                $isHere = $currentUnitId !== null && (int) $stop['unit_id'] === (int) $currentUnitId;
                $metaParts = array_values(array_filter([
                    $stop['at'] ?? null,
                    $stop['worker_name'] ?? null,
                ]));
            @endphp
            <li @class(['wp-round-stops__item', 'wp-round-stops__item--here' => $isHere])>
                <span class="wp-round-stops__index">{{ $index + 1 }}</span>
                <div class="wp-round-stops__body">
                    <div class="wp-round-stops__line">
                        <span class="wp-round-stops__name">{{ $stop['name'] }}</span>
                        <span class="wp-pill wp-pill--xs wp-pill--{{ $pill }}">{{ $label }}</span>
                    </div>
                    @if ($metaParts !== [])
                        <p class="wp-round-stops__meta">{{ implode(' · ', $metaParts) }}</p>
                    @endif
                    @if (($stop['checklist_failed'] ?? []) !== [])
                        <p class="wp-round-stops__failed-label">{{ __('portal.round.checklist_failed') }}</p>
                        <ul class="wp-round-stops__failed">
                            @foreach ($stop['checklist_failed'] as $failedItem)
                                <li class="wp-round-stops__failed-item">{{ $failedItem }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (filled($stop['description'] ?? null))
                        <p class="wp-round-stops__note">{{ $stop['description'] }}</p>
                    @endif
                    @if (! empty($stop['photos']))
                        <div
                            class="wp-round-stops__photos wp-photo-gallery"
                            x-data="{ lightboxSrc: null }"
                            @keydown.escape.window="lightboxSrc = null"
                        >
                            <div class="wp-photo-grid wp-photo-grid--gallery">
                                @foreach ($stop['photos'] as $photo)
                                    <button
                                        type="button"
                                        class="wp-photo-thumb"
                                        wire:key="round-stop-{{ $stop['unit_id'] }}-photo-{{ $photo['id'] }}"
                                        @click="lightboxSrc = @js($photo['url'])"
                                        aria-label="{{ __('issues.show.photo_enlarge') }}"
                                    >
                                        <img
                                            src="{{ $photo['url'] }}"
                                            alt=""
                                            width="48"
                                            height="48"
                                            loading="lazy"
                                            x-on:error="$el.closest('.wp-photo-thumb')?.remove()"
                                        >
                                    </button>
                                @endforeach
                            </div>
                            <div
                                class="wp-photo-lightbox"
                                x-show="lightboxSrc"
                                x-cloak
                                x-transition.opacity
                                role="dialog"
                                aria-modal="true"
                                @click="lightboxSrc = null"
                            >
                                <img :src="lightboxSrc" alt="" @click.stop>
                            </div>
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
