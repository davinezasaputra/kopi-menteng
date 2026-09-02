<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MenuProductImport implements ToCollection, WithHeadingRow
{
    private Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    public function rows(): Collection
    {
        return $this->rows ?? collect();
    }
}
