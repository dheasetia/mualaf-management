<x-filament-panels::page>
<div class="space-y-6">
    <p class="text-sm text-gray-500">
        {{ $lastAggregated === null ? __('reports.labels.not_aggregated') : __('reports.labels.last_aggregated', ['date' => $lastAggregated]) }}
    </p>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($snapshots as $label => $value)
            <x-filament::section>
                <div class="text-sm text-gray-500">{{ $label }} · {{ __('reports.periods.now') }}</div>
                <div class="text-3xl font-semibold">{{ number_format($value) }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="p-2 text-start"></th>
                    <th class="p-2 text-end">{{ __('reports.periods.last_30_days') }}</th>
                    <th class="p-2 text-end">{{ __('reports.periods.this_year') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($flows as $label => [$month, $year])
                    <tr class="border-b">
                        <td class="p-2">{{ $label }}</td>
                        <td class="p-2 text-end">{{ number_format($month) }}</td>
                        <td class="p-2 text-end">{{ number_format($year) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section :heading="__('reports.labels.monthly_conversions')">
        <table class="w-full text-sm">
            <tbody>
                @foreach ($monthly as $month => $value)
                    <tr class="border-b">
                        <td class="p-2" dir="ltr">{{ $month }}</td>
                        <td class="p-2 w-2/3">
                            <div class="h-3 rounded bg-primary-500" style="width: {{ max($monthly) > 0 ? round($value / max($monthly) * 100) : 0 }}%"></div>
                        </td>
                        <td class="p-2 text-end">{{ number_format($value) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
    <div class="grid gap-6 lg:grid-cols-2">
    <x-filament::section :heading="__('reports.labels.by_office')">
        <table class="w-full text-sm">
            <thead><tr class="border-b text-gray-500"><th class="p-2 text-start">{{ __('reports.labels.office') }}</th><th class="p-2 text-end">{{ __('reports.labels.count') }}</th></tr></thead>
            <tbody>
                @forelse ($byOffice as $name => $value)
                    <tr class="border-b"><td class="p-2">{{ $name }}</td><td class="p-2 text-end">{{ number_format($value) }}</td></tr>
                @empty
                    <tr><td class="p-2 text-gray-500" colspan="2">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section :heading="__('reports.labels.by_nationality')">
        <table class="w-full text-sm">
            <thead><tr class="border-b text-gray-500"><th class="p-2 text-start">{{ __('reports.labels.country') }}</th><th class="p-2 text-end">{{ __('reports.labels.count') }}</th></tr></thead>
            <tbody>
                @forelse ($byNationality as $name => $value)
                    <tr class="border-b"><td class="p-2">{{ $name }}</td><td class="p-2 text-end">{{ number_format($value) }}</td></tr>
                @empty
                    <tr><td class="p-2 text-gray-500" colspan="2">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
    </div>
</div>
</x-filament-panels::page>
