<?php


// ============================================================
// FILE: app/Exports/DemoQuestionsSheet.php
// 20 real questions across all 7 types
// ============================================================

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths, WithStrictNullComparison};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DemoQuestionsSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithStrictNullComparison
{
    public function __construct(
        private string $subjectCode,
        private string $topicCode,
    ) {}

    public function title(): string { return 'questions'; }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 14, 'C' => 14, 'D' => 16, 'E' => 11,
            'F' => 55, 'G' => 8, 'H' => 14, 'I' => 14,
            'J' => 25, 'K' => 25, 'L' => 25, 'M' => 25, 'N' => 25,
            'O' => 16, 'P' => 40, 'Q' => 30,
            'R' => 20, 'S' => 8, 'T' => 18, 'U' => 20,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
        ]);
        $sheet->getStyle('F:F')->getAlignment()->setWrapText(true);
        $sheet->getStyle('P:P')->getAlignment()->setWrapText(true);
        return [];
    }

    public function array(): array
    {
        $s = $this->subjectCode;
        $t = $this->topicCode;

        return [
            // Header
            [
                'external_id', 'type', 'subject_code', 'topic_code', 'difficulty',
                'question_text', 'marks', 'negative_marks', 'time_limit_sec',
                'option_a', 'option_b', 'option_c', 'option_d', 'option_e',
                'correct_answer', 'explanation', 'solution_approach',
                'tags', 'language', 'source', 'image_url',
            ],

            // ── MCQ (5 questions) ──

            ['Q001', 'mcq', $s, $t, 'easy',
                'What is the SI unit of force?',
                1, 0.25, 30,
                'Watt', 'Newton', 'Joule', 'Pascal', '',
                'B', 'Force is measured in Newtons (N) in the SI system.', '',
                'physics,units', 'en', 'NCERT Ch1', ''],

            ['Q002', 'mcq', $s, $t, 'medium',
                'Which planet is known as the Red Planet?',
                2, 0.5, 45,
                'Venus', 'Jupiter', 'Mars', 'Saturn', '',
                'C', 'Mars appears red due to iron oxide (rust) on its surface.', '',
                'astronomy,planets', 'en', 'NCERT Ch10', ''],

            ['Q003', 'mcq', $s, $t, 'hard',
                'What is the speed of light in vacuum?',
                2, 0.5, 60,
                '3 × 10⁶ m/s', '3 × 10⁸ m/s', '3 × 10¹⁰ m/s', '3 × 10⁴ m/s', '',
                'B', 'Speed of light c = 3 × 10⁸ m/s (approximately 299,792,458 m/s).', 'Step 1: Recall c ≈ 3 × 10⁸ m/s',
                'physics,optics,pyq', 'en', 'PYQ 2024', ''],

            ['Q004', 'mcq', $s, $t, 'easy',
                'What is the chemical symbol for Gold?',
                1, 0.25, 30,
                'Go', 'Gd', 'Au', 'Ag', '',
                'C', 'Au comes from the Latin word "Aurum" meaning gold.', '',
                'chemistry,elements', 'en', '', ''],

            ['Q005', 'mcq', $s, $t, 'medium',
                'Which organelle is responsible for producing ATP?',
                2, 0.5, 45,
                'Nucleus', 'Ribosome', 'Mitochondria', 'Golgi apparatus', '',
                'C', 'Mitochondria produce ATP through oxidative phosphorylation.', '',
                'biology,cell', 'en', 'NCERT Ch8', ''],

            // ── MULTI-SELECT (3 questions) ──

            ['Q006', 'multi_select', $s, $t, 'medium',
                'Which of the following are noble gases? (Select all correct)',
                3, 0.75, 60,
                'Helium', 'Nitrogen', 'Neon', 'Oxygen', 'Argon',
                'A,C,E', 'Noble gases: He, Ne, Ar, Kr, Xe, Rn. They have full outer shells.', '',
                'chemistry,periodic-table', 'en', 'NCERT Ch5', ''],

            ['Q007', 'multi_select', $s, $t, 'hard',
                'Which of these are Newton\'s laws of motion? (Select all correct)',
                4, 1, 60,
                'Law of Inertia', 'Law of Gravitation', 'F = ma', 'Action-Reaction', 'Conservation of Energy',
                'A,C,D', 'Newton\'s three laws: Inertia (1st), F=ma (2nd), Action-Reaction (3rd).', '',
                'physics,mechanics', 'en', '', ''],

            ['Q008', 'multi_select', $s, $t, 'easy',
                'Which of the following are states of matter?',
                2, 0.5, 45,
                'Solid', 'Liquid', 'Energy', 'Gas', 'Plasma',
                'A,B,D,E', 'The four fundamental states: Solid, Liquid, Gas, Plasma. Energy is not a state of matter.', '',
                'physics,matter', 'en', 'NCERT Ch1', ''],

            // ── TRUE/FALSE (3 questions) ──

            ['Q009', 'true_false', $s, $t, 'easy',
                'Water boils at 100°C at standard atmospheric pressure.',
                1, 0, 20,
                'True', 'False', '', '', '',
                'TRUE', 'At 1 atm (101.325 kPa), water boils at exactly 100°C.', '',
                'physics,thermodynamics', 'en', '', ''],

            ['Q010', 'true_false', $s, $t, 'easy',
                'The Earth is the largest planet in the solar system.',
                1, 0, 20,
                'True', 'False', '', '', '',
                'FALSE', 'Jupiter is the largest planet. Earth is the 5th largest.', '',
                'astronomy', 'en', '', ''],

            ['Q011', 'true_false', $s, $t, 'medium',
                'DNA stands for Deoxyribonucleic Acid.',
                1, 0, 20,
                'True', 'False', '', '', '',
                'TRUE', 'DNA = Deoxyribonucleic Acid, the molecule carrying genetic instructions.', '',
                'biology,genetics', 'en', 'NCERT Ch6', ''],

            // ── SHORT ANSWER (3 questions) ──

            ['Q012', 'short_answer', $s, $t, 'easy',
                'What is the chemical formula for table salt?',
                1, 0, 30,
                '', '', '', '', '',
                'NaCl', 'Table salt is sodium chloride (NaCl).', '',
                'chemistry', 'en', '', ''],

            ['Q013', 'short_answer', $s, $t, 'easy',
                'What is the value of acceleration due to gravity on Earth (in m/s²)?',
                1, 0, 30,
                '', '', '', '', '',
                '9.8', 'Standard value: g = 9.8 m/s² (or more precisely 9.80665 m/s²).', '',
                'physics,gravity', 'en', '', ''],

            ['Q014', 'short_answer', $s, $t, 'medium',
                'Name the process by which plants make their food using sunlight.',
                1, 0, 30,
                '', '', '', '', '',
                'Photosynthesis', 'Plants use chlorophyll to convert CO2 and H2O into glucose using sunlight.', '',
                'biology,botany', 'en', '', ''],

            // ── LONG ANSWER (2 questions) ──

            ['Q015', 'long_answer', $s, $t, 'hard',
                'Explain Newton\'s three laws of motion with real-world examples for each.',
                10, 0, 600,
                '', '', '', '', '',
                '', 'See long_answers sheet for model answer.', '',
                'physics,mechanics,neet-2025', 'en', 'NCERT Ch5', ''],

            ['Q016', 'long_answer', $s, $t, 'hard',
                'Describe the structure of an atom, including the roles of protons, neutrons, and electrons.',
                8, 0, 480,
                '', '', '', '', '',
                '', 'See long_answers sheet for model answer.', '',
                'chemistry,atomic-structure', 'en', 'NCERT Ch2', ''],

            // ── FILL IN THE BLANK (2 questions) ──

            ['Q017', 'fill_blank', $s, $t, 'medium',
                'The {{1}} is the largest organ in the human body, and the {{2}} is the smallest bone.',
                2, 0, 45,
                '', '', '', '', '',
                '', 'Skin is the largest organ. Stapes (in the ear) is the smallest bone.', '',
                'biology,anatomy', 'en', '', ''],

            ['Q018', 'fill_blank', $s, $t, 'easy',
                'The chemical formula for carbon dioxide is {{1}} and for water is {{2}}.',
                2, 0, 30,
                '', '', '', '', '',
                '', 'CO2 is carbon dioxide. H2O is water.', '',
                'chemistry,formulas', 'en', '', ''],

            // ── MATCH THE COLUMN (2 questions) ──

            ['Q019', 'match_column', $s, $t, 'medium',
                'Match the following elements with their chemical symbols:',
                4, 0, 90,
                '', '', '', '', '',
                '', 'See match_pairs sheet for column A and column B.', '',
                'chemistry,elements', 'en', '', ''],

            ['Q020', 'match_column', $s, $t, 'medium',
                'Match the following organs with their primary functions:',
                4, 0, 90,
                '', '', '', '', '',
                '', 'See match_pairs sheet for column A and column B.', '',
                'biology,anatomy', 'en', '', ''],
        ];
    }
}