<?php

// ============================================================
// FILE: app/Exports/DemoFillBlanksSheet.php
// ============================================================

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DemoFillBlanksSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function title(): string { return 'fill_blanks'; }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 14, 'C' => 40, 'D' => 18];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
        ]);
        return [];
    }

    public function array(): array
    {
        return [
            ['external_id', 'blank_number', 'correct_answers', 'is_case_sensitive'],

            // Q017: Skin / Stapes
            ['Q017', 1, 'Skin|skin', 'FALSE'],
            ['Q017', 2, 'Stapes|stapes', 'FALSE'],

            // Q018: CO2 / H2O
            ['Q018', 1, 'CO2|co2', 'FALSE'],
            ['Q018', 2, 'H2O|h2o', 'FALSE'],
        ];
    }
}