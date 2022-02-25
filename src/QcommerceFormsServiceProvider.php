<?php

namespace Qubiqx\QcommerceForms;

use Qubiqx\QcommerceForms\Commands\QcommerceFormsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class QcommerceFormsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('qcommerce-forms')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_qcommerce-forms_table')
            ->hasCommand(QcommerceFormsCommand::class);
    }
}
