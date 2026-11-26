<x-filament-panels::page>
    <p class="text-sm text-gray-500">{{ __('identity.access_review.help') }}</p>
    <x-filament::section>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="p-2 text-start">{{ __('identity.access_review.name') }}</th>
                    <th class="p-2 text-start">{{ __('identity.access_review.roles') }}</th>
                    <th class="p-2 text-start">{{ __('identity.access_review.last_login') }}</th>
                    <th class="p-2 text-start">{{ __('identity.access_review.flags') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b">
                        <td class="p-2">{{ $row['name'] }}<div class="text-xs text-gray-500" dir="ltr">{{ $row['email'] }}</div></td>
                        <td class="p-2">{{ implode('، ', $row['roles']) }}</td>
                        <td class="p-2" dir="ltr">{{ $row['last_login_at']?->setTimezone(config('app.display_timezone'))->format('Y-m-d') ?? __('identity.access_review.never') }}</td>
                        <td class="p-2 text-danger-600">
                            @if ($row['stale']) <div>{{ __('identity.access_review.stale', ['days' => $days]) }}</div> @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
