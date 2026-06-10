@include('components.layouts.error', [
    'code' => '403',
    'title' => __('error.403.title'),
    'message' => __('error.403.message'),
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
