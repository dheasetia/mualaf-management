<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('certificates.verify.title') }}</title>
    <style>
        body { font-family: system-ui, 'Noto Sans Arabic', sans-serif; background: #f3f4f6; margin: 0; padding: 24px; color: #111827; }
        main { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 8px 16px; }
        dt { color: #6b7280; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 999px; font-weight: bold; }
        .valid { background: #d1fae5; color: #065f46; }
        .invalid { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('certificates.verify.title') }}</h1>
    @if ($result === null)
        <p class="status invalid">{{ __('certificates.verify.not_found') }}</p>
    @else
        @php($valid = $result['status'] === \App\Domain\Certificates\Enums\CertificateStatus::Issued)
        <p class="status {{ $valid ? 'valid' : 'invalid' }}">{{ $valid ? __('certificates.verify.valid') : $result['status']->label() }}</p>
        <dl>
            <dt>{{ __('certificates.fields.certificate_number') }}</dt><dd dir="ltr">{{ $result['number'] }}</dd>
            <dt>{{ __('certificates.fields.type') }}</dt><dd>{{ $result['type'] }}</dd>
            <dt>{{ __('certificates.fields.name') }}</dt><dd>{{ $result['name'] }}</dd>
            <dt>{{ __('certificates.fields.issued_on') }}</dt><dd dir="ltr">{{ $result['issued_on'] }}</dd>
            <dt>{{ __('certificates.fields.office') }}</dt><dd>{{ $result['office'] }}</dd>
        </dl>
    @endif
</main>
</body>
</html>
