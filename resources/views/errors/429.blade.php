@include('components.layouts.error', [
    'code' => '429',
    'title' => __('error.429.title'),
    'message' => __('error.429.message'),
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
