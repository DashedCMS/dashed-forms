<?php

namespace Dashed\DashedForms;

use Dashed\DashedForms\Filament\Pages\Settings\FormSettingsPage;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class DashedFormsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-forms';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                FormSettingsPage::class,
            ])
            ->resources([
                FormResource::class,
            ]);
    }

    public function boot(Panel $panel): void {}
}
