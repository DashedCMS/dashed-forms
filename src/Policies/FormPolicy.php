<?php

namespace Dashed\DashedForms\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class FormPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Form';
    }
}
