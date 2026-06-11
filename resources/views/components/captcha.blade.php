@php
    $provider = \Dashed\DashedCore\Models\Customsetting::get(
        'captcha_provider',
        \Dashed\DashedCore\Classes\Sites::getActive(),
        'google_recaptcha'
    );
@endphp

@if($provider === 'google_recaptcha' && \Dashed\DashedCore\Models\Customsetting::get('google_recaptcha_site_key'))
    @livewireRecaptcha(
        version: 'v3',
        siteKey: \Dashed\DashedCore\Models\Customsetting::get('google_recaptcha_site_key'),
        size: 'compact',
    )
@elseif($provider === 'mcaptcha' && \Dashed\DashedCore\Models\Customsetting::get('mcaptcha_site_key') && \Dashed\DashedCore\Models\Customsetting::get('mcaptcha_instance_url'))
    @php
        $mcaptchaInstanceUrl = rtrim(\Dashed\DashedCore\Models\Customsetting::get('mcaptcha_instance_url'), '/');
        $mcaptchaSiteKey = \Dashed\DashedCore\Models\Customsetting::get('mcaptcha_site_key');
    @endphp
    <label id="mcaptcha__token-label" for="mcaptcha__token"
           data-mcaptcha_url="{{ $mcaptchaInstanceUrl }}/widget/?sitekey={{ $mcaptchaSiteKey }}">
        <input type="text" id="mcaptcha__token" name="mcaptcha__token"
               wire:model="mcaptchaToken" hidden />
    </label>
    <div id="mcaptcha__widget-container" wire:ignore></div>
    <script src="https://unpkg.com/@mcaptcha/vanilla-glue@0.1.0-rc2/dist/index.js"></script>
@endif
