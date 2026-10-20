<?php

namespace Dashed\DashedForms\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class FormReplyTemplatePolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'FormReplyTemplate';
    }
}
