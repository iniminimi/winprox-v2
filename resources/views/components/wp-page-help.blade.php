@props(['page' => ''])

@php
    $help = $page !== '' ? \App\Support\PageHelp::for($page) : null;
@endphp

@if ($help && ($help['actions'] !== [] || $help['statuses'] !== []))
    <div class="wp-page-help" x-data="{ open: false }" @keydown.escape.window="open = false">
        <button
            type="button"
            class="wp-page-help-trigger"
            @click="open = true"
            aria-label="{{ __('page-help.button_label') }}"
            title="{{ __('page-help.button_label') }}"
        >?</button>

        <div class="wp-page-help-modal" x-show="open" x-cloak x-transition.opacity>
            <div class="wp-page-help-backdrop" @click="open = false" aria-hidden="true"></div>
            <div
                class="wp-page-help-dialog wp-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="wp-page-help-title-{{ $page }}"
            >
                <div class="wp-page-help-dialog-head">
                    <h2 id="wp-page-help-title-{{ $page }}" class="wp-page-help-dialog-title">{{ $help['title'] }}</h2>
                    <button type="button" class="wp-page-help-close" @click="open = false" aria-label="{{ __('page-help.modal.close') }}">×</button>
                </div>

                <div class="wp-page-help-dialog-body">
                    @if ($help['actions'] !== [])
                        <section class="wp-page-help-section">
                            <h3 class="wp-page-help-section-title">{{ __('page-help.modal.actions_heading') }}</h3>
                            <ul class="wp-page-help-list">
                                @foreach ($help['actions'] as $action)
                                    <li @class(['wp-page-help-item', 'wp-page-help-item--nested' => ! empty($action['nested'])])>
                                        <p class="wp-page-help-item-label">{{ $action['label'] }}</p>
                                        <p class="wp-page-help-item-text">{{ $action['text'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($help['statuses'] !== [])
                        <section class="wp-page-help-section">
                            <h3 class="wp-page-help-section-title">{{ __('page-help.modal.statuses_heading') }}</h3>
                            @if ($help['status_note'])
                                <p class="wp-page-help-note">{{ $help['status_note'] }}</p>
                            @endif
                            <ul class="wp-page-help-list">
                                @foreach ($help['statuses'] as $status)
                                    <li class="wp-page-help-item">
                                        <p class="wp-page-help-item-label">
                                            <span class="wp-pill wp-pill--{{ $status['pill'] }}">{{ $status['label'] }}</span>
                                        </p>
                                        <p class="wp-page-help-item-text">{{ $status['text'] }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>

                <div class="wp-page-help-dialog-foot">
                    <button type="button" class="btn btn--ghost btn--sm" @click="open = false">{{ __('page-help.modal.close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endif
