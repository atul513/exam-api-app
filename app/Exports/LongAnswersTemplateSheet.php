<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Exports/LongAnswersTemplateSheet.php
// ─────────────────────────────────────────────────────────────

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LongAnswersTemplateSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function title(): string { return 'long_answers'; }

    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 70, 'C' => 40, 'D' => 12, 'E' => 12];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
        ]);
        $sheet->getStyle('A2:E2')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fef9c3']],
        ]);
        $sheet->getStyle('B:B')->getAlignment()->setWrapText(true);
        return [];
    }

    public function array(): array
    {
        return [
            ['external_id', 'model_answer', 'keywords', 'min_words', 'max_words'],
            // Demo for DEMO_LONG_005
            [
                'DEMO_LONG_005',
                "Photosynthesis is the process by which green plants, algae, and certain bacteria convert light energy into chemical energy stored in glucose.\n\n1. Light-dependent reactions: Occur in thylakoid membranes. Water is split (photolysis), releasing O2. Light energy is captured by chlorophyll and converted to ATP and NADPH.\n\n2. Light-independent reactions (Calvin Cycle): Occur in the stroma. CO2 is fixed into G3P using ATP and NADPH. G3P is used to synthesize glucose.\n\nOverall equation: 6CO2 + 6H2O + light → C6H12O6 + 6O2",
                'chlorophyll,thylakoid,Calvin cycle,ATP,NADPH,photolysis,CO2 fixation,glucose,stroma',
                150,
                500,
            ],
        ];
    }
}