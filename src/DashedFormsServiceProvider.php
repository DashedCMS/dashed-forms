<?php

namespace Dashed\DashedForms;

use Dashed\DashedForms\Filament\Pages\Settings\FormSettingsPage;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\PluginServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Livewire\Livewire;

class DashedFormsServiceProvider extends PluginServiceProvider
{
    public static string $name = 'dashed-forms';

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

        Livewire::component('dashed-forms.form', Form::class);
    }

    protected function getPages(): array
    {
        return array_merge(parent::getPages(), [
            FormSettingsPage::class,
        ]);
    }

    protected function getResources(): array
    {
        return array_merge(parent::getResources(), [
            FormResource::class,
        ]);
    }
}
