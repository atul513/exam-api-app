<?php

// ============================================================
// FILE: app/Exports/DemoImportExport.php
// Generates a real importable Excel with 20 questions (all 7 types)
// ============================================================

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DemoImportExport implements WithMultipleSheets
{
    public function __construct(
        private string $subjectCode,
        private string $topicCode,
    ) {}

    public function sheets(): array
    {
        return [
            'questions'    => new DemoQuestionsSheet($this->subjectCode, $this->topicCode),
            'fill_blanks'  => new DemoFillBlanksSheet(),
            'match_pairs'  => new DemoMatchPairsSheet(),
            'long_answers' => new DemoLongAnswersSheet(),
        ];
    }
}