<?php

// ============================================================
// FILE: app/Exports/DemoMatchPairsSheet.php
// ============================================================

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DemoMatchPairsSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function title(): string { return 'match_pairs'; }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 30, 'C' => 30, 'D' => 12];
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
            ['external_id', 'column_a', 'column_b', 'sort_order'],

            // Q019: Elements → Symbols
            ['Q019', 'Hydrogen',  'H',  1],
            ['Q019', 'Oxygen',    'O',  2],
            ['Q019', 'Iron',      'Fe', 3],
            ['Q019', 'Sodium',    'Na', 4],

            // Q020: Organs → Functions
            ['Q020', 'Heart',    'Pumps blood',                  1],
            ['Q020', 'Lungs',    'Gas exchange (O2/CO2)',        2],
            ['Q020', 'Liver',    'Detoxification and bile',      3],
            ['Q020', 'Kidneys',  'Filters blood, produces urine', 4],
        ];
    }
}