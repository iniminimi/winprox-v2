<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('email.unsubscribed.title') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="flex justify-end mb-4">
            @include('partials.wp-lang-switch', ['variant' => 'inline'])
        </div>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
        <div class="mb-6">
            <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-4">
            {{ __('email.unsubscribed.heading') }}
        </h1>

        <p class="text-gray-600 mb-6">
            {{ __('email.unsubscribed.message', ['email' => $email]) }}
        </p>

        <div class="mb-6">
            <a href="{{ URL::signedRoute('email.resubscribe', ['t' => request()->query('t')]) }}" class="btn btn--ghost btn--sm">
                {{ __('email.unsubscribed.resubscribe_link') }}
            </a>
        </div>

        <a href="{{ route('welcome') }}" class="btn btn--primary">
            {{ __('email.unsubscribed.back_home') }}
        </a>
        </div>
    </div>
</body>
</html>
