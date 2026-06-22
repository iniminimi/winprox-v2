@props(['size' => 'md', 'visible' => false])

@php
$sizeClasses = match($size) {
    'sm' => 'wp-hourglass--sm',
    'lg' => 'wp-hourglass--lg',
    default => '',
};
@endphp

<span @class(['wp-hourglass', $sizeClasses, 'is-visible' => $visible]) aria-hidden="true">
    <x-wp-icon name="hourglass" />
</span>
