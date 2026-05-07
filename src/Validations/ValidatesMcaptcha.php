<?php

namespace Dashed\DashedForms\Validations;

use Attribute;
use Closure;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportAttributes\Attribute as LivewireAttribute;
use function Livewire\trigger;
use function Livewire\wrap;

#[Attribute]
class ValidatesMcaptcha extends LivewireAttribute
{
    public function call(array $params, Closure $returnEarly): void
    {
        $siteId = Sites::getActive();

        $provider = Customsetting::get('captcha_provider', $siteId, 'google_recaptcha');
        if ($provider !== 'mcaptcha') {
            return;
        }

        $instanceUrl = Customsetting::get('mcaptcha_instance_url', $siteId);
        $siteKey = Customsetting::get('mcaptcha_site_key', $siteId);
        $secret = Customsetting::get('mcaptcha_secret', $siteId);

        if (! $instanceUrl || ! $siteKey || ! $secret) {
            return;
        }

        $token = $this->component->mcaptchaToken ?? '';
        $verifyUrl = rtrim($instanceUrl, '/') . '/api/v1/pow/siteverify';

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->post($verifyUrl, [
                    'token' => $token,
                    'key' => $siteKey,
                    'secret' => $secret,
                ]);
        } catch (ConnectionException $e) {
            // mCaptcha-server unreachable: graceful degradation, let the form through.
            Log::warning('mCaptcha verify unreachable, letting form through', [
                'verify_url' => $verifyUrl,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            // Non-2xx from mCaptcha: graceful degradation, let the form through.
            Log::warning('mCaptcha verify returned non-2xx, letting form through', [
                'verify_url' => $verifyUrl,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $valid = (bool) ($response->json('valid') ?? false);

        if ($valid) {
            $returnEarly(
                wrap($this->component)->{$this->subName}(...$params)
            );

            return;
        }

        $returnEarly(
            trigger('exception', $this->component, ValidationException::withMessages([
                'mcaptchaToken' => Translation::get('mcaptcha-invalid', 'forms', 'De captcha-validatie is mislukt, probeer het opnieuw.'),
            ]), fn () => true)
        );
    }
}
