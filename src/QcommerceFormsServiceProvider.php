<?php

namespace Qubiqx\QcommerceForms;

use Filament\PluginServiceProvider;
use Qubiqx\QcommerceForms\Filament\Pages\Settings\FormSettingsPage;
use Qubiqx\QcommerceForms\Filament\Resources\FormResource;
use Spatie\LaravelPackageTools\Package;

class QcommerceFormsServiceProvider extends PluginServiceProvider
{
    public static string $name = 'qcommerce-forms';

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        cms()->builder(
            'settingPages',
            array_merge(cms()->builder('settingPages'), [
                'formNotifications' => [
                    'name' => 'Formulier notificaties',
                    'description' => 'Beheer meldingen die na het invullen van het formulier worden verstuurd',
                    'icon' => 'bell',
                    'page' => FormSettingsPage::class,
                ],
            ])
        );

        $package
            ->name('qcommerce-forms')
            ->hasRoutes([
                'frontend',
            ])
            ->hasViews();
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
