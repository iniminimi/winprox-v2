{{--
  Talenpillen: schakelen de app-locale via /locale/{locale} (sessie).
  Hergebruikt in de zijbalk (beheer) en op de auth-schermen.
  Props: $variant ('sidebar' default | 'inline').
--}}
@props(['variant' => 'sidebar'])

<div class="wp-lang wp-lang--{{ $variant }}">
    @if ($variant === 'sidebar')
        <span class="wp-lang-label">{{ __('common.language.label') }}</span>
    @endif
    <div class="wp-lang-pills">
        @foreach (config('locales.labels') as $code => $label)
            <a href="{{ route('locale.switch', $code) }}"
               class="wp-lang-pill {{ app()->getLocale() === $code ? 'is-active' : '' }}"
               aria-label="{{ $label }}"
               @if (app()->getLocale() === $code) aria-current="true" @endif>
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
