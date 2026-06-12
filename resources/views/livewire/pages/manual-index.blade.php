@push('manual-print-footer')
<div class="wp-manual-print-footer" aria-hidden="true">
    {{ __('manual.print_footer', ['title' => __($coverPrefix.'.title'), 'tenant' => $tenantName]) }}
</div>
@endpush

<div class="wp-manual-root">
  {{-- Print-knop (verdwijnt bij afdrukken) --}}
  <div class="no-print wp-manual-print-toolbar">
    <div class="wp-manual-print-toolbar__actions">
      <button type="button" class="btn btn--primary" onclick="window.print()">
        {{ __('manual.cover.print') }}
      </button>
      <a href="{{ route('manual.hub') }}" class="btn btn--ghost">{{ __('manual.cover.back') }}</a>
    </div>
    <p class="wp-manual-print-hint">{{ __('manual.cover.print_hint') }}</p>
    <div class="wp-manual-print-toolbar__locales">
      @foreach ($this->availableLocales as $locale)
        <button
          type="button"
          class="btn btn--sm {{ $locale === $lang ? 'btn--primary' : 'btn--ghost' }}"
          wire:click="changeLocale('{{ $locale }}')"
        >
          {{ strtoupper($locale) }}
        </button>
      @endforeach
    </div>
  </div>

  {{-- Cover-pagina --}}
  <div class="wp-manual-cover wp-manual-chapter">
    <div class="wp-manual-cover__logos">
      <img
        class="wp-manual-cover__logo"
        src="{{ asset('images/Winprox_logo_300.png') }}"
        alt="WinProx"
      >
      @if (!empty($tenantLogoUrl))
        <img
          class="wp-manual-cover__tenant-logo"
          src="{{ $tenantLogoUrl }}"
          alt="{{ $tenantName }}"
        >
      @endif
    </div>

    <div class="wp-manual-cover__brand">
      <h1 class="wp-manual-cover__title">
        {{ __($coverPrefix.'.title') }}
      </h1>
      <p class="wp-manual-cover__subtitle">
        {{ __($coverPrefix.'.subtitle') }}
      </p>
      @if (!empty($showTenantNameOnCover) && $tenantName !== '')
        <p class="wp-manual-cover__tenant">{{ $tenantName }}</p>
      @endif
      <p class="wp-manual-cover__meta">
        {{ __('manual.cover.generated', ['date' => $generatedAt]) }}
      </p>
    </div>

    <div id="manual-toc" class="wp-manual-toc">
      <p class="wp-manual-toc-heading">{{ __('manual.cover.contents') }}</p>
      @if (!empty($tocSections))
        <div class="wp-manual-toc-columns">
          @foreach ($tocSections as $section)
            <div class="wp-manual-toc-panel">
              <p class="wp-manual-toc-panel__title">
                <a href="#section-{{ $section['id'] }}">{{ $section['label'] }}</a>
              </p>
              <ol class="wp-manual-toc-panel__list">
                @foreach ($section['chapters'] as $chapter)
                  @php $slug = str_replace('.', '-', $chapter['key']); @endphp
                  <li class="wp-manual-toc-panel__item">
                    @if (!empty($chapter['icon']))
                      <span class="wp-manual-toc-panel__icon" aria-hidden="true">
                        <x-wp-icon :name="$chapter['icon']" />
                      </span>
                    @endif
                    <a href="#chapter-{{ $slug }}">{{ $chapter['title'] }}</a>
                  </li>
                @endforeach
                @if (!empty($section['statusBlock']))
                  <li class="wp-manual-toc-panel__item">
                    <span class="wp-manual-toc-panel__icon" aria-hidden="true">
                      <x-wp-icon name="information-circle" />
                    </span>
                    <a href="#section-{{ $section['id'] }}-statuses">{{ $section['statusBlock']['title'] }}</a>
                  </li>
                @endif
              </ol>
            </div>
          @endforeach
        </div>
      @else
        <div class="wp-manual-toc-columns wp-manual-toc-columns--single">
          <div class="wp-manual-toc-panel">
            <ol class="wp-manual-toc-panel__list">
              @foreach ($chapters as $chapter)
                @php $slug = str_replace('.', '-', $chapter['key']); @endphp
                <li><a href="#chapter-{{ $slug }}">{{ $chapter['title'] }}</a></li>
              @endforeach
            </ol>
          </div>
        </div>
      @endif
    </div>
  </div>

  @if ($showGettingStarted)
  {{-- Pagina 2: Stappenplan --}}
  <div class="wp-manual-chapter" style="
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 100vh;
    padding: 4rem 3rem;
    max-width: 900px;
    margin: 0 auto;
    width: 100%;
  ">
    <p style="
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--wp-accent-text);
      margin: 0 0 1rem;
    ">{{ __('manual.getting_started.label') }}</p>
    <h2 style="
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: -0.03em;
      color: var(--wp-text);
      margin: 0 0 0.5rem;
    ">{{ __('manual.getting_started.title') }}</h2>
    <p style="
      color: var(--wp-text-body);
      font-size: 1rem;
      margin: 0 0 3rem;
    ">{{ __('manual.getting_started.intro') }}</p>

    <ol style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1.25rem;">
      @foreach (range(1, 5) as $i)
        @php $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT); @endphp
        <li style="
          display: grid;
          grid-template-columns: 3rem 1fr;
          gap: 1.25rem;
          align-items: start;
          padding: 1.25rem 1.5rem;
          background: var(--wp-surface);
          border: 1px solid var(--wp-border);
          border-radius: 12px;
        ">
          <span style="
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--wp-accent-text);
            line-height: 1;
            padding-top: 0.1rem;
          ">{{ $num }}</span>
          <div>
            <p style="font-weight: 700; color: var(--wp-text); margin: 0 0 0.35rem; font-size: 1rem;">{{ __('manual.step_' . $i . '_title') }}</p>
            <p style="color: var(--wp-text-body); font-size: 0.9rem; line-height: 1.6; margin: 0;">{{ __('manual.step_' . $i . '_text') }}</p>
          </div>
        </li>
      @endforeach
    </ol>
  </div>
  @endif

  {{-- Hoofdstukken --}}
  @php $chapterOffset = 0; @endphp
  @if (!empty($tocSections))
    @foreach ($tocSections as $section)
      <div
        id="section-{{ $section['id'] }}"
        style="
        padding: 2.5rem 2.5rem 1rem;
        max-width: 900px;
        margin: 0 auto;
        width: 100%;
        border-top: 2px solid var(--wp-accent);
      ">
        <p style="
          font-size: 0.75rem;
          font-weight: 700;
          text-transform: uppercase;
          letter-spacing: 0.12em;
          color: var(--wp-accent-text);
          margin: 0 0 1rem;
        ">{{ $section['label'] }}</p>
        <h2 style="
          font-size: 2rem;
          font-weight: 700;
          letter-spacing: -0.03em;
          color: var(--wp-text);
          margin: 0 0 0.5rem;
        ">{{ $section['title'] }}</h2>
        <p style="
          color: var(--wp-text-body);
          font-size: 1rem;
          margin: 0;
        ">{{ $section['intro'] }}</p>
      </div>

      @foreach ($section['chapters'] as $chapter)
        @php
          $index = $chapterOffset;
          $chapterOffset++;
          $isLast = $chapterOffset === count($chapters);
          $slug = str_replace('.', '-', $chapter['key']);
        @endphp
        @include('livewire.pages.partials.manual-chapter', [
          'chapter' => $chapter,
          'index' => $index,
          'slug' => $slug,
          'isLast' => $isLast,
        ])
      @endforeach

      @if (!empty($section['statusBlock']))
        @include('livewire.pages.partials.manual-section-statuses', [
          'block' => $section['statusBlock'],
          'sectionId' => $section['id'],
        ])
      @endif
    @endforeach
  @else
    @foreach ($chapters as $index => $chapter)
      @php
        $isLast = $loop->last;
        $slug = str_replace('.', '-', $chapter['key']);
      @endphp
      @include('livewire.pages.partials.manual-chapter', [
        'chapter' => $chapter,
        'index' => $index,
        'slug' => $slug,
        'isLast' => $isLast,
      ])
    @endforeach
  @endif

  {{-- Footer --}}
  <div style="
    text-align: center;
    padding: 2rem;
    border-top: 1px solid var(--wp-border);
    color: var(--wp-text-muted);
    font-size: 0.8rem;
    max-width: 900px;
    margin: 0 auto;
  ">
    {{ __($footerKey, ['date' => $generatedAt]) }}
  </div>
</div>
