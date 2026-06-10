@include('components.layouts.error', [
    'code' => '404',
    'title' => __('error.404.title'),
    'message' => __('error.404.message'),
    'primaryAction' => [
        'label' => __('error.action.home'),
        'url' => route('dashboard'),
    ],
    'secondaryAction' => [
        'label' => __('error.action.back'),
        'url' => '#',
        'onclick' => 'history.back(); return false;',
    ],
])
