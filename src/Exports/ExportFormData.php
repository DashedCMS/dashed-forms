<?php

namespace Dashed\DashedForms\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ExportFormData implements FromArray
{
    /**
     * @return \Illuminate\Support\Collection
     */

    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->records as $record) {
            if ($record->content) {
                $data[] = $record->content;
            } else {
                $inputData = [];

                foreach ($record->formFields as $field) {
                    $inputData[] = $field->value;
                }

                $data[] = $inputData;
            }
        }

        return $data;
    }
}
