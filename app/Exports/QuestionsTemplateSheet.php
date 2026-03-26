<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Exports/QuestionsTemplateSheet.php
// ─────────────────────────────────────────────────────────────

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths, WithStrictNullComparison};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class QuestionsTemplateSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithStrictNullComparison
{
    public function __construct(
        private ?string $subjectCode = null,
        private ?string $topicCode = null,
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
        // Header row: bold + background color
        $sheet->getStyle('A1:U1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'ffffff']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a56db']],
        ]);

        // Demo rows: light yellow background
        $sheet->getStyle('A2:U8')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fef9c3']],
        ]);

        // Wrap text for question and option columns
        $sheet->getStyle('F:F')->getAlignment()->setWrapText(true);
        $sheet->getStyle('J:N')->getAlignment()->setWrapText(true);
        $sheet->getStyle('P:P')->getAlignment()->setWrapText(true);

        return [];
    }

    public function array(): array
    {
        $sub = $this->subjectCode ?? 'PHY';
        $top = $this->topicCode ?? 'NEWTON_LAWS';

        $headers = [
            'external_id', 'type', 'subject_code', 'topic_code', 'difficulty',
            'question_text', 'marks', 'negative_marks', 'time_limit_sec',
            'option_a', 'option_b', 'option_c', 'option_d', 'option_e',
            'correct_answer', 'explanation', 'solution_approach',
            'tags', 'language', 'source', 'image_url',
        ];

        $demos = [
            // 1. MCQ
            [
                'DEMO_MCQ_001', 'mcq', $sub, $top, 'medium',
                'Which of Newton\'s laws explains why a ball rolling on a surface eventually stops?',
                2, 0.5, 60,
                'First Law (Inertia)', 'Second Law (F=ma)', 'Third Law (Action-Reaction)', 'Law of Gravitation', '',
                'B',
                'Friction is an unbalanced force. By F=ma, it decelerates the ball to zero velocity.',
                'Step 1: Identify forces\nStep 2: Apply F=ma',
                'neet-2025,mechanics', 'en', 'NCERT Ch5', '',
            ],
            // 2. Multi-Select
            [
                'DEMO_MULTI_002', 'multi_select', $sub, $top, 'hard',
                'Which of the following are renewable energy sources? (Select all that apply)',
                4, 1, 90,
                'Solar Energy', 'Coal', 'Wind Energy', 'Natural Gas', 'Hydroelectric',
                'A,C,E',
                'Solar, Wind, and Hydroelectric are renewable as they are naturally replenished.',
                '',
                'environment,energy', 'en', '', '',
            ],
            // 3. True/False
            [
                'DEMO_TF_003', 'true_false', $sub, $top, 'easy',
                'The mitochondria is known as the powerhouse of the cell.',
                1, 0, 30,
                'True', 'False', '', '', '',
                'TRUE',
                'Mitochondria produce ATP through cellular respiration.',
                '',
                'biology,cell', 'en', 'NCERT Ch8', '',
            ],
            // 4. Short Answer
            [
                'DEMO_SHORT_004', 'short_answer', $sub, $top, 'easy',
                'What is the chemical formula for water?',
                1, 0, 30,
                '', '', '', '', '',
                'H2O',
                'Water has two hydrogen atoms bonded to one oxygen atom.',
                '',
                'chemistry', 'en', '', '',
            ],
            // 5. Long Answer
            [
                'DEMO_LONG_005', 'long_answer', $sub, $top, 'hard',
                'Explain the process of photosynthesis in detail, including both light-dependent and light-independent reactions.',
                10, 0, 600,
                '', '', '', '', '',
                '',
                'See "long_answers" sheet for model answer, keywords, and word limits.',
                '',
                'biology,botany', 'en', 'NCERT Ch13', '',
            ],
            // 6. Fill in the Blank
            [
                'DEMO_FILL_006', 'fill_blank', $sub, $top, 'medium',
                'The {{1}} is the largest planet in our solar system, and {{2}} is the smallest planet.',
                2, 0, 45,
                '', '', '', '', '',
                '',
                'Jupiter has diameter 139,820 km. Mercury has diameter 4,879 km.',
                '',
                'astronomy,solar-system', 'en', '', '',
            ],
            // 7. Match the Column
            [
                'DEMO_MATCH_007', 'match_column', $sub, $top, 'medium',
                'Match the following scientists with their discoveries:',
                4, 0, 120,
                '', '', '', '', '',
                '',
                'See "match_pairs" sheet for Column A and Column B pairs.',
                '',
                'general-science', 'en', '', '',
            ],
        ];

        return array_merge([$headers], $demos);
    }
}
