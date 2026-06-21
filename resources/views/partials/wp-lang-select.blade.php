{{--
  Taalkeuze als dropdown (V1 welcome-hub patroon: details/summary).
  driver=route → /locale/{code}  |  driver=livewire → switchLocale() op portaal
--}}
@php
    $variant = $variant ?? 'inline';
    $driver = $driver ?? 'route';
    $current = app()->getLocale();
    $labels = config('locales.labels', []);
    $currentLabel = $labels[$current] ?? strtoupper($current);
@endphp

@if ($variant === 'sidebar')
    <span class="wp-lang-label">{{ __('common.language.label') }}</span>
@endif

<details class="wp-lang-select wp-lang-select--{{ $variant }}">
    <summary class="wp-lang-select-trigger" aria-label="{{ __('common.language.label') }}">
        <svg class="wp-lang-select-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
        </svg>
        <span class="notranslate" translate="no">{{ $currentLabel }}</span>
        <svg class="wp-lang-select-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </summary>
    <div class="wp-lang-select-menu" role="list">
        @foreach ($labels as $code => $label)
            @if ($driver === 'livewire')
                <button type="button"
                        role="listitem"
                        wire:key="lang-{{ $code }}"
                        wire:click="switchLocale('{{ $code }}')"
                        @class(['wp-lang-select-option', 'is-active' => $current === $code])
                        @if ($current === $code) aria-current="true" @endif>
                    <span class="notranslate" translate="no">{{ $label }}</span>
                </button>
            @else
                <a href="{{ route('locale.switch', $code) }}"
                   role="listitem"
                   @class(['wp-lang-select-option', 'is-active' => $current === $code])
                   @if ($current === $code) aria-current="true" @endif>
                    <span class="notranslate" translate="no">{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </div>
</details>
