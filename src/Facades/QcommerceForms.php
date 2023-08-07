<?php

namespace Qubiqx\QcommerceForms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Qubiqx\QcommerceForms\QcommerceForms
 */
class QcommerceForms extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'qcommerce-forms';
    }
}
