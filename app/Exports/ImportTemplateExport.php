<?php
// ============================================================
// ─── app/Exports/ImportTemplateExport.php ───────────────────
// ============================================================

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{WithMultipleSheets};

class ImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'questions'    => new TemplateSheet([
                'external_id', 'type', 'subject_code', 'topic_code', 'difficulty',
                'question_text', 'marks', 'negative_marks', 'time_limit_sec',
                'option_a', 'option_b', 'option_c', 'option_d', 'option_e',
                'correct_answer', 'explanation', 'solution_approach',
                'tags', 'language', 'source', 'image_url',
            ]),
            'fill_blanks'  => new TemplateSheet([
                'external_id', 'blank_number', 'correct_answers', 'is_case_sensitive',
            ]),
            'match_pairs'  => new TemplateSheet([
                'external_id', 'column_a', 'column_b', 'sort_order',
            ]),
            'long_answers' => new TemplateSheet([
                'external_id', 'model_answer', 'keywords', 'min_words', 'max_words',
            ]),
        ];
    }
}
