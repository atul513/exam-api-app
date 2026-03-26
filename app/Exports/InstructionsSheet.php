<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithTitle, WithStyles, WithColumnWidths};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InstructionsSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    public function title(): string { return 'instructions'; }

    public function columnWidths(): array
    {
        return ['A' => 35, 'B' => 80];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1  => ['font' => ['bold' => true, 'size' => 14]],
            3  => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1a56db']]],
            13 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1a56db']]],
            24 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1a56db']]],
            33 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1a56db']]],
            39 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1a56db']]],
        ];
    }

    public function array(): array
    {
        return [
            ['Question Import — Instructions', ''],
            ['', ''],
            ['GENERAL RULES', ''],
            ['Field', 'Description'],
            ['external_id', 'Unique ID for each question (e.g., Q001, Q002). Must not repeat within the file.'],
            ['type', 'One of: mcq, multi_select, true_false, short_answer, long_answer, fill_blank, match_column'],
            ['subject_code', 'Must match an existing subject code (e.g., PHY, CHEM, BIO). See your admin panel for codes.'],
            ['topic_code', 'Must match an existing topic code under the given subject (e.g., NEWTON_LAWS).'],
            ['difficulty', 'One of: easy, medium, hard, expert'],
            ['marks', 'Positive number (e.g., 1, 2, 4). Decimal allowed (1.5).'],
            ['negative_marks', 'Optional. Marks deducted for wrong answer (e.g., 0.25). Default: 0'],
            ['tags', 'Comma-separated slugs (e.g., neet-2025,pyq,mechanics). Optional.'],
            ['', ''],
            ['MCQ / MULTI-SELECT / TRUE-FALSE', ''],
            ['option_a to option_e', 'Fill option text. At least 2 required for MCQ. True/False uses only A and B.'],
            ['correct_answer', 'MCQ: Single letter (e.g., B). Multi-select: Comma-separated (e.g., A,C,E). True/False: TRUE or FALSE'],
            ['', ''],
            ['Example MCQ', 'correct_answer = B (means option_b is correct)'],
            ['Example Multi-Select', 'correct_answer = A,C,E (means options A, C, and E are correct)'],
            ['Example True/False', 'option_a = True, option_b = False, correct_answer = TRUE'],
            ['', ''],
            ['Note for Multi-Select', 'Must have at least 2 correct answers. Separate with comma, no spaces around letters.'],
            ['Note for True/False', 'option_a MUST be "True" and option_b MUST be "False". correct_answer is TRUE or FALSE.'],
            ['', ''],
            ['FILL IN THE BLANK', ''],
            ['question_text', 'Use {{1}}, {{2}} etc. as placeholders. Example: The {{1}} is the largest planet.'],
            ['fill_blanks sheet', 'Add matching rows in the "fill_blanks" sheet with same external_id and blank_number.'],
            ['correct_answers', 'Pipe-separated alternatives (e.g., Jupiter|jupiter). Not case-sensitive by default.'],
            ['', ''],
            ['Note', 'Number of {{n}} placeholders in question text must match rows in fill_blanks sheet.'],
            ['', ''],
            ['SHORT ANSWER', ''],
            ['correct_answer', 'Put the expected answer in the correct_answer column (e.g., H2O).'],
            ['', ''],
            ['LONG ANSWER', ''],
            ['correct_answer', 'Optional short version. For full model answer, use the "long_answers" sheet.'],
            ['long_answers sheet', 'Add model_answer, keywords, min_words, max_words for the same external_id.'],
            ['', ''],
            ['MATCH THE COLUMN', ''],
            ['question_text', 'General instruction (e.g., "Match the following scientists with their discoveries")'],
            ['match_pairs sheet', 'Add rows in "match_pairs" sheet with column_a and column_b for same external_id.'],
            ['', ''],
            ['TIPS', ''],
            ['', 'The "questions" sheet has demo rows for every type. Delete them before importing your own.'],
            ['', 'If subject_code or topic_code is wrong, that row will be skipped (check error report after import).'],
            ['', 'You can import a mix of all types in the same file.'],
            ['', 'Maximum 10,000 rows per file. For larger imports, split into multiple files.'],
        ];
    }
}
