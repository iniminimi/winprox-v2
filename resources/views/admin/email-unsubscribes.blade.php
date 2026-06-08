<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.email_unsubscribe.title') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-6xl mx-auto p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ __('admin.email_unsubscribe.title') }}</h1>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" action="{{ route('admin.email-unsubscribes.index') }}" class="mb-6">
            <div class="flex gap-2">
                <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('admin.email_unsubscribe.search_placeholder') }}" class="flex-1 px-4 py-2 border rounded">
                <button type="submit" class="btn btn--primary">{{ __('admin.email_unsubscribe.search') }}</button>
            </div>
        </form>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('admin.email_unsubscribe.email') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('admin.email_unsubscribe.user') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('admin.email_unsubscribe.unsubscribed_at') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('admin.email_unsubscribe.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($rows as $row)
                        @php
                            $matchedUser = $matchedUsers[\App\Models\EmailUnsubscribe::normalizeEmail($row->email)] ?? null;
                        @endphp
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $row->email }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($matchedUser)
                                    <a href="{{ route('platform.users', ['search' => $matchedUser->email]) }}" class="text-blue-600 hover:text-blue-800">
                                        {{ $matchedUser->name }}
                                    </a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row->unsubscribed_at->format('d-m-Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <form method="POST" action="{{ route('admin.email-unsubscribes.destroy', $row) }}" class="inline" onsubmit="return confirm('{{ __('admin.email_unsubscribe.confirm_restore') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-green-600 hover:text-green-800">
                                        {{ __('admin.email_unsubscribe.restore') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </div>
</body>
</html>
