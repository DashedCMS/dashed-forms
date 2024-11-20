<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Middleware\FrontendMiddleware;
use Dashed\DashedForms\Controllers\Frontend\FormController;
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
        Route::post('/form/post', [FormController::class, 'store'])->name('dashed.frontend.forms.store');
    }
);
