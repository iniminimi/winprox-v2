@props([
    'title',
    'documentTitle' => null,
    'tenant',
    'columns',
    'rows',
    'truncated' => false,
    'limit' => null,
    'emptyMessage' => null,
])

@php
    $organisationLogoUrl = $tenant->logoPublicUrl();
    $organisationAddress = $tenant->organisationAddressLine();
    $documentTitle = $documentTitle ?? $title;
@endphp

<x-layouts.print :title="$documentTitle">
    <div class="wp-container wp-stack">
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title :title="$title" />
                <div class="wp-cluster wp-no-print">
                    <button type="button" class="btn btn--primary btn--sm" onclick="window.print()">
                        {{ __('reports.print_button') }}
                    </button>
                </div>
                @if ($truncated)
                    <p class="wp-flash wp-flash--muted">
                        {{ __('reports.truncated', ['limit' => $limit ?? \App\Support\Reports\ListExportLimit::MAX]) }}
                    </p>
                @endif
            </div>
            <div class="wp-cluster wp-cluster--tight wp-page-actions">
                <div class="wp-sidebar-header-logo">
                    <img
                        src="{{ $organisationLogoUrl ?? asset('images/Winprox_logo_100.png') }}"
                        alt="{{ $tenant->name }}"
                    >
                </div>
                <p class="wp-muted">
                    <strong class="wp-text-body">{{ $tenant->name }}</strong>
                    @if ($organisationAddress)
                        <br>{{ $organisationAddress }}
                    @endif
                </p>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="wp-card wp-card-pad">
                <p class="wp-muted">{{ $emptyMessage ?? __('reports.empty') }}</p>
            </div>
        @else
            <div class="wp-card wp-card-pad">
                <table>
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="wp-muted wp-text-sm">{{ __('reports.row_count', ['count' => $rows->count()]) }}</p>
            </div>
        @endif
    </div>
</x-layouts.print>
