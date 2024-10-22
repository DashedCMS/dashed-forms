<?php

namespace Dashed\DashedForms;

class FormManager
{
    protected static $builders = [
        'webhookClasses' => [],
        'apiClasses' => [],
    ];

    public function builder(string $name, null|string|array $blocks = null): self|array
    {
        if (! $blocks) {
            return static::$builders[$name] ?? [];
        }

        static::$builders[$name] = $blocks;

        return $this;
    }
}
