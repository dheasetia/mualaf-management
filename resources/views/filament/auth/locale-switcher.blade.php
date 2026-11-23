@php($other = \App\Support\Localization\Locales::other(app()->getLocale()))
<form method="POST" action="{{ route('locale.switch', $other) }}" class="text-center">
    @csrf
    <button type="submit" class="text-sm font-medium text-primary-600 hover:underline" lang="{{ $other }}">
        {{ \App\Support\Localization\Locales::nativeName($other) }}
    </button>
</form>
