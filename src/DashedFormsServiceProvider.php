<?php

namespace Dashed\DashedForms;

use Livewire\Livewire;
use Illuminate\Support\Facades\Blade;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Gate;
use Dashed\DashedForms\Livewire\Form;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedForms\Commands\SendApisForFormInputs;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedForms\Commands\SendWebhooksForFormInputs;
use Dashed\DashedForms\Filament\Pages\Settings\FormSettingsPage;

class DashedFormsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-forms';

    public function bootingPackage()
    {
        cms()->registerNavigationGroup('Formulieren', 50);

        cms()
            ->registerMailable(\Dashed\DashedForms\Mail\CustomFormSubmitConfirmationMail::class)
            ->registerMailable(\Dashed\DashedForms\Mail\AdminCustomFormSubmitConfirmationMail::class)
            ->registerMailable(\Dashed\DashedForms\Mail\FormInputsExportMail::class)
            ->emailBlock(\Dashed\DashedForms\Mail\EmailBlocks\FormSubmissionBlock::key(), \Dashed\DashedForms\Mail\EmailBlocks\FormSubmissionBlock::class);

        Livewire::component('dashed-forms.form', Form::class);

        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(SendWebhooksForFormInputs::class)->everyMinute();
            $schedule->command(SendApisForFormInputs::class)->hourly();
        });

        config(['services.google.recaptcha.site_key' => Customsetting::get('google_recaptcha_site_key', Sites::getActive(), '')]);
        config(['services.google.recaptcha.secret_key' => Customsetting::get('google_recaptcha_secret_key', Sites::getActive(), '')]);
        config(['services.mcaptcha.instance_url' => Customsetting::get('mcaptcha_instance_url', Sites::getActive(), '')]);
        config(['services.mcaptcha.site_key' => Customsetting::get('mcaptcha_site_key', Sites::getActive(), '')]);
        config(['services.mcaptcha.secret' => Customsetting::get('mcaptcha_secret', Sites::getActive(), '')]);

        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components', 'dashed-forms');

        Blade::directive('captchaFormAttributes', function () {
            return "<?php
                \$__captchaProvider = \\Dashed\\DashedCore\\Models\\Customsetting::get(
                    'captcha_provider',
                    \\Dashed\\DashedCore\\Classes\\Sites::getActive(),
                    'google_recaptcha'
                );
                if (\$__captchaProvider === 'google_recaptcha' && \\Dashed\\DashedCore\\Models\\Customsetting::get('google_recaptcha_site_key')) {
                    echo 'wire:recaptcha';
                }
            ?>";
        });

        Gate::policy(\Dashed\DashedForms\Models\Form::class, \Dashed\DashedForms\Policies\FormPolicy::class);

        cms()->registerRolePermissions('Formulieren', [
            'view_form' => 'Formulieren bekijken',
            'edit_form' => 'Formulieren bewerken',
            'delete_form' => 'Formulieren verwijderen',
        ]);

        cms()->builder('summaryContributors', array_merge(
            cms()->builder('summaryContributors') ?? [],
            [\Dashed\DashedForms\Services\Summary\FormSummaryContributor::class],
        ));

        cms()->registerResourceDocs(
            resource: \Dashed\DashedForms\Filament\Resources\FormResource::class,
            title: 'Formulieren',
            intro: 'Met de formulier builder maak je zelf formulieren voor op de website, bijvoorbeeld een contactformulier, een offerteaanvraag of een inschrijving. Je bepaalt welke velden erin komen en wat er met de inzendingen gebeurt.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe formulieren opbouwen met flexibele velden.
- Bestaande formulieren aanpassen of tijdelijk offline zetten.
- De inzendingen per formulier bekijken via de knop "Bekijk aanvragen".
- Meldingen instellen voor wie een bericht moet krijgen bij een nieuwe inzending.
MARKDOWN,
                ],
                [
                    'heading' => 'Velden toevoegen',
                    'body' => <<<MARKDOWN
Een formulier bouw je op uit losse velden die je onder elkaar plaatst. Beschikbare types zijn onder andere:

- **Tekst** voor korte antwoorden zoals een naam of onderwerp.
- **Textarea** voor een langer bericht.
- **Checkbox** voor een akkoord of meerdere keuzes.
- **Radio** om maximaal een optie te laten kiezen.
- **Select** voor een keuzemenu met meerdere opties.
- **Afbeelding upload** zodat bezoekers een foto of document kunnen meesturen.
MARKDOWN,
                ],
                [
                    'heading' => 'Koppelingen en inzendingen',
                    'body' => 'Wat er met een inzending gebeurt bepaal je zelf. Je kunt de gegevens doorsturen naar een webhook of externe API, of ze direct in een mailing provider zetten zodat nieuwe inschrijvers in je nieuwsbrief belanden. Alle inzendingen bewaar je ook in het CMS zelf. In het navigatie menu zie je een badge met het aantal nieuwe inzendingen, en via de knop "Bekijk aanvragen" open je het overzicht van alle ontvangen berichten.',
                ],
            ],
            tips: [
                'Houd formulieren kort, hoe minder velden hoe meer mensen het invullen.',
                'Maak alleen echt noodzakelijke velden verplicht.',
                'Test een nieuw formulier altijd een keer zelf voor je hem live zet.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedForms\Filament\Pages\Settings\FormSettingsPage::class,
            title: 'Formulieren instellingen',
            intro: 'Hier regel je alles rond formulier inzendingen: naar welke e-mailadressen meldingen gaan, welke captcha (Google reCAPTCHA of zelf-gehoste mCaptcha) er gebruikt wordt tegen spam, en of inzendingen automatisch in ActiveCampaign komen. De meeste velden zijn per site.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => 'Je legt per site de notificatie e-mailadressen vast, koppelt eventueel Google reCAPTCHA en vult ActiveCampaign credentials in. Daarnaast kies je globaal of formulier redirects via de server of via JavaScript verlopen.',
                ],
                [
                    'heading' => 'Welke captcha kies je?',
                    'body' => 'Bij **Captcha provider** kies je per site welke captcha er actief is. **Geen** schakelt captcha uit. **Google reCAPTCHA** vraagt een site key en secret key. **mCaptcha** is zelf-gehost (proof-of-work, geen Google) en vraagt een instance URL, sitekey en secret.',
                ],
                [
                    'heading' => 'Hoe koppel je Google reCAPTCHA?',
                    'body' => <<<MARKDOWN
1. Ga naar [google.com/recaptcha/admin](https://www.google.com/recaptcha/admin) en log in met je Google account.
2. Klik op **Een site toevoegen** en geef je website een herkenbare naam.
3. Kies het type reCAPTCHA dat je wil gebruiken en voeg je domeinnaam toe.
4. Accepteer de voorwaarden en klik op opslaan.
5. Kopieer de **Site key** en de **Secret key** en plak ze hieronder.
6. Test een formulier op je site om te controleren of de bescherming werkt.
MARKDOWN,
                ],
                [
                    'heading' => 'Hoe koppel je mCaptcha?',
                    'body' => <<<MARKDOWN
1. Zorg dat er een mCaptcha-instance draait (zie [mcaptcha.org](https://mcaptcha.org)).
2. Maak in mCaptcha een sitekey aan voor het domein van deze website.
3. Kopieer de **instance URL** (bijv. `https://captcha.example.com`), de **sitekey** en de **secret**.
4. Plak ze in de bijbehorende velden hieronder.
5. Test een formulier op je site om te controleren of de proof-of-work werkt.
MARKDOWN,
                ],
                [
                    'heading' => 'Hoe koppel je ActiveCampaign?',
                    'body' => <<<MARKDOWN
1. Log in op je ActiveCampaign account.
2. Ga naar **Settings > Developer**.
3. Kopieer de **API URL** en de **API Key** die je daar ziet staan.
4. Plak beide waarden in de bijbehorende velden hieronder.
5. Doe een testinzending om te controleren of de inzending in ActiveCampaign verschijnt.
MARKDOWN,
                ],
            ],
            fields: [
                'Notificatie e-mailadressen' => 'De e-mailadressen die een melding krijgen zodra iemand een formulier invult. Je kunt meerdere adressen toevoegen door telkens op enter te drukken.',
                'Captcha provider' => 'Kies per site welke captcha er actief is: Geen, Google reCAPTCHA of mCaptcha (self-hosted). De velden hieronder verschijnen op basis van de keuze.',
                'Google Recaptcha site key' => 'De publieke site key van Google reCAPTCHA. Deze haal je op uit de reCAPTCHA admin nadat je je domein hebt toegevoegd. De site key wordt zichtbaar in de browser geladen.',
                'Google Recaptcha secret key' => 'De geheime sleutel van Google reCAPTCHA, hoort bij dezelfde site als de site key. Deze sleutel mag niet openbaar worden gedeeld en is verplicht zodra je een site key invult.',
                'mCaptcha instance URL' => 'De basis URL van je zelf-gehoste mCaptcha-instance, bv. https://captcha.example.com. Widget en verify endpoints worden hieruit afgeleid.',
                'mCaptcha sitekey' => 'De publieke sitekey van mCaptcha. Wordt in de browser geladen om het widget te tonen.',
                'mCaptcha secret' => 'De geheime sleutel die hoort bij de sitekey. Wordt alleen server-side gebruikt en niet in de HTML geladen.',
                'ActiveCampaign API URL' => 'De API URL van je ActiveCampaign account. Deze vind je in ActiveCampaign onder Settings, sectie Developer. Het is de basis URL waar inzendingen naartoe worden gestuurd.',
                'ActiveCampaign API key' => 'De API sleutel die hoort bij je ActiveCampaign account. Ook deze vind je onder Settings, sectie Developer. Zonder geldige sleutel komen er geen contacten in ActiveCampaign terecht.',
                'Server side redirects' => 'Aan zorgt dat de redirect na een succesvolle inzending door de server wordt gedaan, wat netter is voor zoekmachines. Uit doet de redirect via JavaScript in de browser, handig als je een single page achtige flow gebruikt.',
            ],
            tips: [
                'Vul minstens een notificatie e-mailadres in, anders krijgt niemand bericht van nieuwe inzendingen.',
                'Vul nooit alleen een reCAPTCHA site key in zonder secret key. Het formulier weigert dan alle inzendingen.',
                'Bij mCaptcha geldt: als de mCaptcha-server tijdelijk onbereikbaar is, laat het formulier de inzending door. Anders kan niemand het formulier nog gebruiken bij een storing.',
            ],
        );
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../resources/templates' => resource_path('views/' . config('dashed-core.site_theme', 'dashed')),
            __DIR__ . '/../resources/component-templates' => resource_path('views/components'),
        ], 'dashed-templates');

        cms()->registerSettingsPage(FormSettingsPage::class, 'Formulier instellingen', 'bell', 'Beheer instellingen voor de formulieren');

        $package
            ->name('dashed-forms')
            ->hasRoutes([
                'frontend',
            ])
            ->hasCommands([
                SendWebhooksForFormInputs::class,
                SendApisForFormInputs::class,
            ])
            ->hasViews();

        cms()->builder('plugins', [
            new DashedFormsPlugin(),
        ]);
    }
}
