<?php


use Dashed\DashedForms\FormManager;

if (! function_exists('forms')) {
    function forms(): FormManager
    {
        return app(FormManager::class);
    }
}
