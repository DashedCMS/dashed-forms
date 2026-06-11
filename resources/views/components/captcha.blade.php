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
        // Unique IDs per form instance. The official @mcaptcha/vanilla-glue
        // library is hardcoded to a single set of element IDs
        // (#mcaptcha__widget-container / #mcaptcha__token-label / #mcaptcha__token)
        // and resolves them with getElementById, so it can only ever drive ONE
        // widget per page. When a second mCaptcha form is present (a second
        // Dashed form, or an embedded Ternair form that bundles its own glue),
        // both initialisations target the first container and dump their iframes
        // into it, leaving the other form empty. We therefore mount the widget
        // iframe ourselves with unique IDs and scope the postMessage token to
        // this specific iframe, so any number of widgets can coexist.
        $mcaptchaUid = 'mcaptcha_' . uniqid();
    @endphp
    <input type="text" id="{{ $mcaptchaUid }}__token" name="mcaptcha__token"
           wire:model="mcaptchaToken" hidden />
    <div id="{{ $mcaptchaUid }}__widget" wire:ignore></div>
    <script>
        (function () {
            var container = document.getElementById(@js($mcaptchaUid . '__widget'));
            var input = document.getElementById(@js($mcaptchaUid . '__token'));

            if (!container || !input || container.dataset.mcaptchaMounted) {
                return;
            }
            container.dataset.mcaptchaMounted = '1';

            var widgetUrl = @js($mcaptchaInstanceUrl . '/widget/?sitekey=' . $mcaptchaSiteKey);
            var widgetHost = new URL(widgetUrl).host;

            var iframe = document.createElement('iframe');
            iframe.title = 'mCaptcha';
            iframe.src = widgetUrl;
            iframe.id = @js($mcaptchaUid . '__iframe');
            iframe.name = @js($mcaptchaUid . '__iframe');
            iframe.scrolling = 'no';
            iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts allow-popups');
            iframe.style.width = '100%';
            iframe.style.height = '80px';
            iframe.style.border = '0';
            container.appendChild(iframe);

            window.addEventListener('message', function (event) {
                // Only accept the token from THIS widget's iframe, even when
                // several widgets share the same mCaptcha instance/origin.
                if (event.source !== iframe.contentWindow) {
                    return;
                }
                if (new URL(event.origin).host !== widgetHost) {
                    return;
                }
                if (event.data && typeof event.data.token !== 'undefined') {
                    input.value = event.data.token;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        })();
    </script>
@endif
