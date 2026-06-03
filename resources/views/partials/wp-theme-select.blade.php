{{--
  WinProx-stijl als dropdown (zelfde patroon als taalkeuze).
--}}
@php
    use App\Enums\UiTheme;
    use App\Support\Ui\UiThemeResolver;

    $variant = $variant ?? 'sidebar';
    $driver = $driver ?? 'route';
    $current = UiTheme::tryFromString(
        $driver === 'livewire'
            ? UiThemeResolver::resolvePortal()
            : UiThemeResolver::resolve(auth()->user()),
    );
@endphp

@if ($variant === 'sidebar')
    <span class="wp-lang-label">{{ __('common.style.label') }}</span>
@endif

<details class="wp-lang-select wp-lang-select--{{ $variant }}">
    <summary class="wp-lang-select-trigger" aria-label="{{ __('settings.style.title') }}">
        <x-wp-icon name="eye" class="wp-lang-select-icon" />
        <span>{{ __('settings.style.options.'.$current->value.'.label') }}</span>
        <svg class="wp-lang-select-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </summary>
    <div class="wp-lang-select-menu" role="list">
        @foreach (UiTheme::choices() as $choice)
            @if ($driver === 'livewire')
                <button type="button"
                        role="listitem"
                        wire:key="theme-{{ $choice->value }}"
                        wire:click="switchUiTheme('{{ $choice->value }}')"
                        @class(['wp-lang-select-option', 'is-active' => $current === $choice])
                        @if ($current === $choice) aria-current="true" @endif>
                    {{ __('settings.style.options.'.$choice->value.'.label') }}
                </button>
            @else
                <a href="{{ route('ui-theme.switch', $choice->value) }}"
                   role="listitem"
                   @class(['wp-lang-select-option', 'is-active' => $current === $choice])
                   @if ($current === $choice) aria-current="true" @endif>
                    {{ __('settings.style.options.'.$choice->value.'.label') }}
                </a>
            @endif
        @endforeach
    </div>
</details>
