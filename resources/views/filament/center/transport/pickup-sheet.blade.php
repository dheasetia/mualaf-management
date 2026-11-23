<x-filament-panels::page>
    @if ($sheet !== null)
        <div class="space-y-4">
            <div class="flex flex-wrap gap-6 text-sm">
                <div><strong>{{ __('transport.fields.departs_at') }}:</strong> <span dir="ltr">{{ $sheet['trip']['departs_at'] }}</span></div>
                <div><strong>{{ __('transport.fields.returns_at') }}:</strong> <span dir="ltr">{{ $sheet['trip']['returns_at'] }}</span></div>
                <div><strong>{{ __('transport.fields.vehicle') }}:</strong> {{ $sheet['trip']['vehicle'] }}</div>
                <div><strong>{{ __('transport.fields.driver') }}:</strong> {{ $sheet['trip']['driver'] }}</div>
                @if ($sheet['trip']['destination'])
                    <div><strong>{{ __('transport.fields.pickup_address') }}:</strong> {{ $sheet['trip']['destination'] }}</div>
                @endif
            </div>

            @if ($sheet['withheld'] > 0)
                <p class="text-sm text-warning-600 print:hidden">{{ __('transport.sheet.withheld', ['count' => $sheet['withheld']]) }}</p>
            @endif

            @if ($sheet['lines'] === [])
                <p>{{ __('transport.sheet.empty') }}</p>
            @else
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="border-b">
                            <th class="p-2 text-start">{{ __('transport.fields.time') }}</th>
                            <th class="p-2 text-start">{{ __('transport.fields.name') }}</th>
                            <th class="p-2 text-start">{{ __('transport.fields.pickup_address') }}</th>
                            <th class="p-2 text-start">{{ __('transport.fields.contact_person') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sheet['lines'] as $line)
                            <tr class="border-b">
                                <td class="p-2" dir="ltr">{{ $line['time'] }}</td>
                                <td class="p-2">{{ $line['nickname'] }}</td>
                                <td class="p-2">
                                    {{ $line['address'] ?? '—' }}
                                    @if ($line['map'])
                                        <a href="{{ $line['map'] }}" class="text-primary-600 print:hidden" target="_blank" rel="noopener">{{ __('transport.fields.map') }}</a>
                                    @endif
                                </td>
                                <td class="p-2">{{ $line['contact_name'] ?? '—' }} <span dir="ltr">{{ $line['contact_phone'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <x-filament::button class="print:hidden" onclick="window.print()" icon="heroicon-o-printer">{{ __('transport.sheet.print') }}</x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
