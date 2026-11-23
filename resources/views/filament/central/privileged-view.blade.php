<x-filament-panels::page>
    <p class="text-sm font-semibold text-danger-600">{{ __('privileged.view_notice') }}</p>
    @foreach ($sections as $scope => $values)
        <x-filament::section :heading="$labels[$scope] ?? $scope">
            <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                @forelse ($values as $label => $value)
                    <div><dt class="text-gray-500">{{ in_array($scope, ['identity', 'contacts'], true) ? $label : ($fieldLabels[$label] ?? $label) }}</dt><dd>{{ $value ?? '—' }}</dd></div>
                @empty
                    <div class="text-gray-500">—</div>
                @endforelse
            </dl>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
