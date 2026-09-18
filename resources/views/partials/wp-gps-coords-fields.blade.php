@php
    $latProperty = $latProperty ?? 'locationFormLatitude';
    $lngProperty = $lngProperty ?? 'locationFormLongitude';
    $applyMethod = $applyMethod ?? 'applyLocationGpsPair';
    $searchProperties = $searchProperties ?? [];
    $searchQuery = $searchQuery ?? '';
    $hintKey = $hintKey ?? 'locations.fields.coords_hint';
    $latError = $latError ?? 'latitude';
    $lngError = $lngError ?? 'longitude';
    $labelKey = $labelKey ?? 'locations.fields.coords';
@endphp
<div
    class="wp-field wp-gps-coords"
    x-data="wpGpsCoordsFields({
        latProperty: @js($latProperty),
        lngProperty: @js($lngProperty),
        applyMethod: @js($applyMethod),
        searchProperties: @js($searchProperties),
        searchQuery: @js($searchQuery),
    })"
>
    <span class="wp-label">{{ __($labelKey) }}</span>
    <div class="wp-gps-coords__row">
        <a
            class="btn btn--ghost wp-gps-coords__maps"
            href="https://www.google.com/maps"
            :href="mapsUrl()"
            target="_blank"
            rel="noopener noreferrer"
            @click="markMapsOpened()"
            aria-label="{{ __('locations.fields.maps_open') }}"
            title="{{ __('locations.fields.maps_open') }}"
        >
            @include('partials.wp-gps-pin-icon', ['class' => 'wp-gps-coords__pin'])
        </a>
        <input
            type="text"
            class="wp-input wp-gps-coords__pair"
            x-model="pair"
            @paste="onPaste($event)"
            @blur="onBlur()"
            inputmode="decimal"
            autocomplete="off"
            placeholder="{{ __('locations.fields.coords_placeholder') }}"
        >
    </div>
    <p class="wp-hint">{{ __($hintKey) }}</p>
    @error($latError) <span class="wp-error">{{ $message }}</span> @enderror
    @error($lngError) <span class="wp-error">{{ $message }}</span> @enderror
</div>
