<?php

namespace Qubiqx\QcommerceForms\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;

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
            $data[] = $record->content;
        }

        return $data;
    }
}
