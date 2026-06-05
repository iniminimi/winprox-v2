@props(['size' => 'md'])

@php
$sizeClasses = match($size) {
    'sm' => 'wp-spinner--sm',
    'lg' => 'wp-spinner--lg',
    default => '',
};
@endphp

<span class="wp-spinner {{ $sizeClasses }}"></span>
