# Changelog

All notable changes to `dashed-forms` will be documented in this file.

## v4.2.2 - 2026-05-11

### Fixed
- **`FormSettingsPage::submit()` crashte met `Undefined array key "google_recaptcha_site_key_{site}"` zodra `captcha_provider` op iets anders dan `google_recaptcha` stond.** Filament v4 omit `visible()`-guarded fields uit `getState()`, waardoor de directe array-indexering een notice/error gooide. Alle `getState()`-accesses gebruiken nu `?? null` (resp. `?? []` voor emails en `?? true` voor de redirect-toggle) en de state wordt één keer opgehaald i.p.v. per setting.

## v4.2.1 - 2026-05-11

### Fixed
- **`EmailCapture` werd niet aangeroepen voor e-mail-velden in contact-/nieuwsbrief-formulieren.** In v4.2.0 controleerde de Livewire `Form::submit()` op `$field->type === 'email'`, maar `dashed-forms` gebruikt `type='input'` met `input_type='email'`. Conditie aangepast naar `type='input' && input_type='email'` óf een waarde die `FILTER_VALIDATE_EMAIL` passeert (vangt ook generieke text-velden met geldige adressen).
- **`FormController::store()` (HTTP / non-Livewire) voedde de captura niet.** Dezelfde captura-loop toegevoegd zodat config-builder-formulieren ook werken.

## v4.2.0 - 2026-05-11

### Added
- **Form-submit voedt globale e-mail-captura.** `Livewire\Form::submit()` roept `Dashed\DashedCore\Classes\EmailCapture::capture()` aan voor elk veld met `type='email'` zodra het ingevulde adres opgeslagen is, met `source='form:{form name}'`. Hierdoor is het adres cross-page bereikbaar via `$capturedEmail` / `capturedEmail()` (vereist dashed-core ≥ v4.7.0).

## v4.1.2 - 2026-05-07

### Added
- `Dashed\DashedForms\Jobs\SyncFormInputApisJob` — queueable job die per `FormInput` `sendApis()` aanroept, refreshet en `viewed=1` zet wanneer `api_send=1`. `tries=3`, `timeout=120`, idempotent (skipt als `should_send_api` false is of `api_send` al 1).
- `Form::submit()` (Livewire) en `FormController::submit()` dispatchen `SyncFormInputApisJob` direct na het opslaan van het FormInput (Livewire: na het schrijven van de FormInputFields), wanneer `should_send_api` is geset. Zo wordt de externe API-sync onmiddellijk via de queue gedaan in plaats van te wachten op de cron.

### Changed
- `dashed:send-apis-for-form-inputs`-command dispatcht nu `SyncFormInputApisJob` per achterblijvend FormInput in plaats van de sync inline te doen. De command is daarmee een fallback/reaper voor inputs die om welke reden dan ook niet via de submit-dispatch verwerkt zijn.
- Schedule van `dashed:send-apis-for-form-inputs` van `everyMinute()` naar `hourly()`. De directe submit-dispatch is nu de hot path; de hourly run vangt alleen achterblijvers op.

## v4.1.1 - 2026-05-07

### Added
- mCaptcha (https://mcaptcha.org/) als alternatieve captcha-provider naast Google reCAPTCHA. Per site instelbaar via `FormSettingsPage` (`captcha_provider` setting met opties `none` / `google_recaptcha` / `mcaptcha`). Vraagt drie nieuwe Customsetting-velden voor mCaptcha: `mcaptcha_instance_url`, `mcaptcha_site_key`, `mcaptcha_secret`. Server-side validatie via `Dashed\DashedForms\Validations\ValidatesMcaptcha` LivewireAttribute (graceful fallback bij non-2xx of connection exception, met `Log::warning` voor operationele zichtbaarheid). Client-side widget via mCaptcha vanilla-glue script (CDN).
- `<x-dashed-forms::captcha />`, `<x-dashed-forms::captcha-errors />` en `@captchaFormAttributes` Blade directive — gedeelde bouwstenen die in form-templates de juiste captcha-markup renderen op basis van de actieve provider. Consumer-projecten kunnen ze direct gebruiken; nieuwe providers later toevoegen vergt geen aanpassingen in de project-templates.

### Changed
- `Dashed\DashedForms\Validations\ValidatesRecaptcha` bailt nu vroeg als `captcha_provider !== 'google_recaptcha'`. Default voor sites zonder expliciete setting blijft `google_recaptcha` zodat bestaand gedrag onveranderd is.

## v4.1.0 - 2026-05-07

### Added
- `FormSummaryContributor` (`src/Services/Summary/FormSummaryContributor.php`) voor de admin samenvatting-mails. Levert een sectie "Formulieren" met het totaal aantal nieuwe inzendingen in de periode plus een tabel met de verdeling per formulier (kolommen: Formulier, Aantal inzendingen). Gegroepeerd op `form_id`, met `whereBetween` op `created_at` zodat de standaard timestamp-index gebruikt wordt. Returnt `null` als er geen inzendingen zijn zodat de sectie wordt overgeslagen. Geregistreerd via `cms()->builder('summaryContributors', ...)` in `DashedFormsServiceProvider::bootingPackage()`. Vereist dashed-core v4.5.0+.

## v4.0.23 - 2026-05-03

### Added
- `form-components/file.blade.php` view. The `file` input type was already wired in `Forms::availableInputTypes()` and the Livewire `Form` component already supports uploads via `WithFileUploads` (`updated()` stores the upload to the `dashed` disk and writes the path back to `values`), but the matching blade was missing - rendering a form with a `file` field threw `Unable to locate a class or view for component [form-components.file]`. Added the view with label, file input wired via `wire:model`, an inline upload-progress indicator, an "uploaded" confirmation, helper text and validation error rendering.

## v4.0.22 - 2026-05-02

### Added
- `popupApiClasses` builder-key in `FormManager`. Provider-packages (`dashed-laposta`, `dashed-ternair`) registreren hier hun popup-newsletter API class zodat `dashed-popups` per popup een repeater kan opbouwen met de geregistreerde providers. Identiek patroon als `apiClasses` (forms) en `orderApiClasses` (orders).

## v4.0.21 - 2026-04-27

- `DashedFormsServiceProvider::bootingPackage()` registreert de "Formulieren" navigatiegroep via `cms()->registerNavigationGroup('Formulieren', 50)`. Vereist dashed-core v4.2.0+.
- Code-style cleanup in `2026_04_18_000001_refresh_admin_form_templates_with_submission_block` migratie.

## 1.0.0 - 202X-XX-XX

- initial release
