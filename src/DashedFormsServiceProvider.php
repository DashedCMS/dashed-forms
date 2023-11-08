<?php

namespace Dashed\DashedForms;

use Livewire\Livewire;
use Dashed\DashedForms\Livewire\Form;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedForms\Filament\Pages\Settings\FormSettingsPage;

class DashedFormsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-forms';

    public function bootingPackage()
    {
        Livewire::component('dashed-forms.form', Form::class);
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        cms()->builder(
            'settingPages',
            array_merge(cms()->builder('settingPages'), [
                'formNotifications' => [
                    'name' => 'Formulier instellingen',
                    'description' => 'Beheer instellingen voor de formulieren',
                    'icon' => 'bell',
                    'page' => FormSettingsPage::class,
                ],
            ])
        );

        $package
            ->name('dashed-forms')
            ->hasRoutes([
                'frontend',
            ])
            ->hasViews();

    }
}
