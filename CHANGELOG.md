# Changelog

All notable changes to `dashed-forms` will be documented in this file.

## v4.6.2 - 2026-08-18

### Fixed
- **De opties van radio-, checkbox- en select-velden werden nooit automatisch vertaald.** Ze staan in een repeater onder de sleutel `name`, en die sleutel staat in vrijwel elk project op `ignorableKeysForTranslations` om de structuur van builder-blokken te beschermen. De extractie sloeg daardoor elke optie over: de Vertaal-knop vulde `name`, `placeholder` en `helper_text` in alle talen, terwijl `options` leeg bleef en op het scherm terugviel op de brontaal. Hetzelfde gold voor de namen bij een select-image-veld. Beide kolommen worden nu aangemeld als gewone inhoud via `cms()->builder('plainContentColumnsForTranslations', ...)`, waarvoor `dashed-translations` v4.5.0 of hoger nodig is; op een oudere versie is de registratie een no-op. Bestaande formulieren moeten één keer opnieuw door de Vertaal-knop om de ontbrekende talen te vullen.

## v4.2.21 - 2026-08-07

### Fixed
- **Taalselector op het formulier-scherm liet de vorige taal overschrijven.** De velden staan in een repeater met relatie, die de locale-switcher niet meenam. De state werd daarom met de hand in `$this->data` gezet, waarna de onderliggende repeater-velden hun oude keys hielden, de validatie op 'verplicht' omviel en de switcher `activeLocale` terugzette op de vorige taal — terwijl het scherm de nieuwe toonde. Opslaan overschreef vervolgens de vorige taal met de inhoud van de nieuwe. De state wordt nu opnieuw ingeladen via `loadStateFromRelationships()`, zodat Filament zelf de uuid-keys van de repeater-items opbouwt.
- **Melding bij een taalwissel noemde niet welke velden ontbraken.** De `ValidationException` werd ongebonden gevangen, waardoor de veldnamen die de validator meelevert werden weggegooid ten gunste van een vaste tekst. De melding somt nu per veld op wat er misging, met de naam van het formulierveld erbij (opgehaald uit een taal die hem wél heeft, want juist in de taal waar het misgaat is die leeg). Bij meer dan zes meldingen wordt de lijst afgekapt.
- **Gedupliceerde formulieren blokkeerden elke opslag.** `duplicate()` nam `email_confirmation_form_field_id` letterlijk over, terwijl de gekopieerde velden nieuwe id's kregen. Die pointer wees dus naar een veld van het bronformulier, stond niet in de optielijst van de select en liet de validatie bij élke opslag omvallen — en omdat de taalselector opslaat voordat hij wisselt, kreeg je die melding bij iedere taalwissel, ook in talen waarin niets ontbrak. De pointer verhuist nu mee naar de kopie; een pointer naar een veld van een ander formulier vervalt bij het inladen, en de controle vóór opslaan kijkt niet langer alleen of het veld bestáát maar ook of het bij dit formulier hoort.

## v4.2.18 - 2026-06-11

### Fixed
- **mCaptcha-widget werd nooit gerenderd in de Dashed Livewire-form (lege container).** De widget werd gemount via een plain inline `<script>` binnen de Livewire-component; Livewire v3 voert die niet betrouwbaar uit, en `@script` keyt op `$this` — binnen de anonieme `<x-dashed-forms::captcha />`-component is dat niet de Livewire-component, dus dat zou ook nooit draaien. De mount gebeurt nu via **Alpine `x-init`** (wordt altijd verwerkt, ongeacht hoe het element in de DOM komt) met config via `data-*`-attributen.
- **Eén actieve widget per pagina naast een extern formulier.** Als er al een externe mCaptcha-widget op de pagina staat (bv. een embedded Ternair-form, dat via `@mcaptcha/vanilla-glue` z'n iframe altijd id `mcaptcha-widget__iframe` geeft) wijkt de Dashed-form daarvoor. Dit onderdrukt géén andere Dashed-forms (die krijgen hun eigen unieke widget), dus meerdere Dashed-forms op één pagina blijven elk werken.

## v4.2.16 - 2026-06-11

### Fixed
- **mCaptcha rendrde dubbel / in het verkeerde formulier wanneer er meerdere mCaptcha-formulieren op één pagina stonden.** De `@mcaptcha/vanilla-glue` library is hardcoded op één set element-IDs (`#mcaptcha__widget-container` / `#mcaptcha__token-label` / `#mcaptcha__token`) en resolvet die met `getElementById`, waardoor elke initialisatie (een tweede Dashed-form, of een embedded Ternair-form dat z'n eigen glue meelevert) z'n iframe in dezelfde eerste container dumpte. De widget wordt nu zelf gemount met **unieke IDs per form-instance** en de postMessage-token wordt gescoped op het eigen iframe (`event.source === iframe.contentWindow`), zodat een willekeurig aantal widgets naast elkaar kan bestaan. De externe `vanilla-glue`-dependency (unpkg) is hiermee vervallen. Het verborgen tokenveld behoudt `name="mcaptcha__token"` + `wire:model="mcaptchaToken"`, dus server-side `ValidatesMcaptcha` blijft ongewijzigd werken.

## v4.2.14 - 2026-06-11

### Fixed
- **mCaptcha-widget flikkerde en verdween zodra de gebruiker begon te typen.** De `@mcaptcha/vanilla-glue` library injecteert een iframe in `#mcaptcha__widget-container`, maar Livewire's morph (door `wire:model.live`-velden) herstructureerde die container bij elke toetsaanslag, waardoor het iframe verloren ging en de widget zich steeds opnieuw probeerde te initialiseren. De container heeft nu `wire:ignore` zodat Livewire dit element met rust laat. Het verborgen token-veld (`#mcaptcha__token`) blijft via `wire:model` werken.

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
