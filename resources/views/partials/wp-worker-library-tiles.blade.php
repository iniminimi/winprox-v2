@if ($showDocuments || $showAnnouncements)
    <div class="wp-tiles">
        @if ($showDocuments)
            <button type="button" class="wp-tile" wire:click="openTeamDocuments">
                <span class="wp-cluster">
                    <x-wp-icon name="document" class="wp-tile-icon" />
                    <span class="wp-tile-title">{{ __('portal.tiles.documents') }}</span>
                    <span class="wp-pill wp-pill--{{ $documentUnreadCount > 0 ? 'new' : 'closed' }}">
                        {{ $documentUnreadCount > 0 ? $documentUnreadCount : $documentCount }}
                    </span>
                </span>
                <span class="wp-tile-sub">{{ __('portal.worker_library.documents_sub') }}</span>
            </button>
        @endif

        @if ($showAnnouncements)
            <button type="button" class="wp-tile" wire:click="openTeamAnnouncements">
                <span class="wp-cluster">
                    <x-wp-icon name="chat-bubble" class="wp-tile-icon" />
                    <span class="wp-tile-title">{{ __('portal.tiles.announcements') }}</span>
                    <span class="wp-pill wp-pill--{{ $announcementUnreadCount > 0 ? 'new' : 'closed' }}">
                        {{ $announcementUnreadCount > 0 ? $announcementUnreadCount : $announcementCount }}
                    </span>
                </span>
                <span class="wp-tile-sub">{{ __('portal.worker_library.announcements_sub') }}</span>
            </button>
        @endif
    </div>
@endif
