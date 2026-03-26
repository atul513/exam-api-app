<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Exports/FillBlanksTemplateSheet.php
// ─────────────────────────────────────────────────────────────

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FillBlanksTemplateSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function title(): string { return 'fill_blanks'; }

    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 14, 'C' => 40, 'D' => 18];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
        ]);
        $sheet->getStyle('A2:D3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fef9c3']],
        ]);
        return [];
    }

    public function array(): array
    {
        return [
            ['external_id', 'blank_number', 'correct_answers', 'is_case_sensitive'],
            // Demo for DEMO_FILL_006
            ['DEMO_FILL_006', 1, 'Jupiter|jupiter', 'FALSE'],
            ['DEMO_FILL_006', 2, 'Mercury|mercury', 'FALSE'],
        ];
    }
}