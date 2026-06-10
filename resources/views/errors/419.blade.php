@include('components.layouts.error', [
    'code' => '419',
    'title' => __('error.419.title'),
    'message' => __('error.419.message'),
    'primaryAction' => [
        'label' => __('error.action.login'),
        'url' => route('login'),
    ],
    'secondaryAction' => [
        'label' => __('error.action.home'),
        'url' => route('dashboard'),
    ],
])
