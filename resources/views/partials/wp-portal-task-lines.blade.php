{{-- Taak (nr …) + omschrijving; daaronder Melding (nr …) + omschrijving/datum (kleiner). --}}
@props(['task', 'issue'])

@php
    $taskDescription = trim($task->localizedDescription());
@endphp

<p class="wp-text-body">{{ __('portal.worker.task_heading', ['nr' => $task->id]) }}</p>
@if ($taskDescription !== '')
    <p class="wp-text-body">{{ $taskDescription }}</p>
@endif

@include('partials.wp-portal-issue-line', ['issue' => $issue, 'secondary' => true])
