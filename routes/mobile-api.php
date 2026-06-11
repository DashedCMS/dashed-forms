<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedForms\Http\Controllers\Api\V1\FormInputController;

// Mobiele API voor formulier-aanvragen. Dezelfde beveiliging als de andere
// mobiele endpoints: Sanctum-token + site-context + ability-check.
Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function (): void {
        Route::get('forms', [FormInputController::class, 'forms'])->middleware('ability:forms.read');
        Route::get('form-inputs', [FormInputController::class, 'index'])->middleware('ability:forms.read');
        Route::get('form-inputs/{formInput}', [FormInputController::class, 'show'])->middleware('ability:forms.read');
        Route::post('form-inputs/{formInput}/viewed', [FormInputController::class, 'markViewed'])->middleware('ability:forms.read');
        Route::post('form-inputs/{formInput}/draft-reply', [FormInputController::class, 'draftReply'])->middleware('ability:forms.read');
        Route::post('form-inputs/{formInput}/reply', [FormInputController::class, 'sendReply'])->middleware('ability:forms.read');
    });
