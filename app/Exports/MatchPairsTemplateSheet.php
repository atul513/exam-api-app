<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Exports/MatchPairsTemplateSheet.php
// ─────────────────────────────────────────────────────────────

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MatchPairsTemplateSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function title(): string { return 'match_pairs'; }

    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 30, 'C' => 30, 'D' => 12];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
        ]);
        $sheet->getStyle('A2:D5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fef9c3']],
        ]);
        return [];
    }

    public function array(): array
    {
        return [
            ['external_id', 'column_a', 'column_b', 'sort_order'],
            // Demo for DEMO_MATCH_007
            ['DEMO_MATCH_007', 'Isaac Newton',    'Laws of Motion',        1],
            ['DEMO_MATCH_007', 'Albert Einstein',  'Theory of Relativity', 2],
            ['DEMO_MATCH_007', 'Marie Curie',      'Radioactivity',        3],
            ['DEMO_MATCH_007', 'Charles Darwin',    'Theory of Evolution',  4],
        ];
    }
}