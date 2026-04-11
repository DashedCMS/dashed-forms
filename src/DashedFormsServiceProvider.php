<?php

namespace Dashed\DashedForms;

use Livewire\Livewire;
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
        cms()
            ->registerMailable(\Dashed\DashedForms\Mail\FormSubmitConfirmationMail::class)
            ->registerMailable(\Dashed\DashedForms\Mail\AdminFormSubmitConfirmationMail::class)
            ->registerMailable(\Dashed\DashedForms\Mail\CustomFormSubmitConfirmationMail::class)
            ->registerMailable(\Dashed\DashedForms\Mail\AdminCustomFormSubmitConfirmationMail::class)
            ->registerMailable(\Dashed\DashedForms\Mail\FormInputsExportMail::class);

        Livewire::component('dashed-forms.form', Form::class);

        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(SendWebhooksForFormInputs::class)->everyMinute();
            $schedule->command(SendApisForFormInputs::class)->everyMinute();
        });

        config(['services.google.recaptcha.site_key' => Customsetting::get('google_recaptcha_site_key', Sites::getActive(), '')]);
        config(['services.google.recaptcha.secret_key' => Customsetting::get('google_recaptcha_secret_key', Sites::getActive(), '')]);
        Gate::policy(\Dashed\DashedForms\Models\Form::class, \Dashed\DashedForms\Policies\FormPolicy::class);

        cms()->registerRolePermissions('Formulieren', [
            'view_form' => 'Formulieren bekijken',
            'edit_form' => 'Formulieren bewerken',
            'delete_form' => 'Formulieren verwijderen',
        ]);
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
