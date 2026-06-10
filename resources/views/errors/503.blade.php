@include('components.layouts.error', [
    'code' => '503',
    'title' => __('error.503.title'),
    'message' => __('error.503.message'),
    'primaryAction' => [
        'label' => __('error.action.retry'),
        'url' => '#',
        'onclick' => 'window.location.reload(); return false;',
    ],
    'secondaryAction' => [
        'label' => __('error.action.home'),
        'url' => route('dashboard'),
    ],
])
