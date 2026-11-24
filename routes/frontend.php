<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Middleware\FrontendMiddleware;
use Dashed\DashedForms\Controllers\Frontend\FormController;
use Dashed\DashedForms\Controllers\Frontend\PublicFormController;
use Dashed\LaravelLocalization\Facades\LaravelLocalization;
use Dashed\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Dashed\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Dashed\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => array_merge(['web', FrontendMiddleware::class, LocaleSessionRedirect::class, LaravelLocalizationRedirectFilter::class, LaravelLocalizationViewPath::class], cms()->builder('frontendMiddlewares')),
    ],
    function () {
        //Form routes
        Route::post('/form/post', [FormController::class, 'store'])->middleware('throttle:20,1')->name('dashed.frontend.forms.store');

        // Standalone public page per form, so a form can be shared by URL
        // without building a page around it. Prefix is configurable per project.
        Route::get('/' . trim(config('dashed-forms.public_route_prefix', 'aanmelden'), '/') . '/{slug}', PublicFormController::class)
            ->name('dashed.frontend.forms.public');
    }
);
