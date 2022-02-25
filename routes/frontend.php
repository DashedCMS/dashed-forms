<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Qubiqx\QcommerceCore\Middleware\FrontendMiddleware;
use Qubiqx\QcommerceForms\Controllers\Frontend\FormController;

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => array_merge(['web', FrontendMiddleware::class, LocaleSessionRedirect::class, LaravelLocalizationRedirectFilter::class, LaravelLocalizationViewPath::class], cms()->builder('frontendMiddlewares')),
    ],
    function () {
        //Form routes
        Route::post('/form/post', [FormController::class, 'store'])->name('qcommerce.frontend.forms.store');
    }
);
