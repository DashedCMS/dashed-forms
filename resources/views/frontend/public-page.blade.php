{{-- Minimal fallback. Projects override this with {theme}/forms/public-page.blade.php. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @livewireStyles
</head>
<body style="font-family: sans-serif; max-width: 48rem; margin: 2rem auto; padding: 0 1rem;">
    <h1>{{ $title }}</h1>
    @if($form->public_intro)
        <div>{!! cms()->convertToHtml($form->public_intro) !!}</div>
    @endif
    <livewire:dashed-forms.form :formId="$form->id"/>
    @livewireScripts
</body>
</html>
