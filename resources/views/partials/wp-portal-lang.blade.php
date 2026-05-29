{{-- Taalkeuze-pillen voor de publieke portalen (roept switchLocale aan). --}}
<div class="wp-lang-pills wp-lang-pills--portal" role="group" aria-label="{{ __('common.language.label') }}">
    @foreach (config('locales.labels', []) as $code => $label)
        <button type="button"
                wire:key="lang-{{ $code }}"
                wire:click="switchLocale('{{ $code }}')"
                @class(['wp-lang-pill', 'is-active' => app()->getLocale() === $code])>
            {{ $label }}
        </button>
    @endforeach
</div>
