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
    {{-- Mounted via Alpine x-init: a plain <script> inside a Livewire v3
         component is not executed reliably, and @script keys on $this which is
         the anonymous Blade component here (not the Livewire component), so it
         would never run. Alpine processes the element regardless. Config is
         passed through data-* attributes to avoid quote conflicts in x-init. --}}
    <div
        id="{{ $mcaptchaUid }}__widget"
        wire:ignore
        x-data
        data-mcaptcha-url="{{ $mcaptchaInstanceUrl }}/widget/?sitekey={{ $mcaptchaSiteKey }}"
        data-mcaptcha-token-id="{{ $mcaptchaUid }}__token"
        x-init="
            (() => {
                if ($el.dataset.mcaptchaMounted) return;
                // Defer to an existing external mCaptcha widget (e.g. an embedded
                // Ternair form, which renders via @mcaptcha/vanilla-glue and always
                // gives its iframe the id 'mcaptcha-widget__iframe'). Keeps a single
                // visible widget on such pages WITHOUT suppressing other Dashed
                // forms, whose widgets carry their own unique iframe IDs.
                if (document.getElementById('mcaptcha-widget__iframe')) return;
                $el.dataset.mcaptchaMounted = '1';

                const widgetUrl = $el.dataset.mcaptchaUrl;
                const widgetHost = new URL(widgetUrl).host;
                const input = document.getElementById($el.dataset.mcaptchaTokenId);

                const iframe = document.createElement('iframe');
                iframe.title = 'mCaptcha';
                iframe.src = widgetUrl;
                iframe.scrolling = 'no';
                iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts allow-popups');
                iframe.style.width = '100%';
                iframe.style.height = '80px';
                iframe.style.border = '0';
                $el.appendChild(iframe);

                window.addEventListener('message', (event) => {
                    // Only accept the token from THIS widget's iframe, even when
                    // several widgets share the same mCaptcha instance/origin.
                    if (event.source !== iframe.contentWindow) return;
                    if (new URL(event.origin).host !== widgetHost) return;
                    if (event.data && typeof event.data.token !== 'undefined') {
                        input.value = event.data.token;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            })()
        "
    ></div>
@endif
