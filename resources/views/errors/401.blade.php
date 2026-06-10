@include('components.layouts.error', [
    'code' => '401',
    'title' => __('error.401.title'),
    'message' => __('error.401.message'),
    'primaryAction' => [
        'label' => __('error.action.login'),
        'url' => route('login'),
    ],
    'secondaryAction' => [
        'label' => __('error.action.home'),
        'url' => route('dashboard'),
    ],
])
