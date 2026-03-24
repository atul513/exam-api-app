<?php


// ============================================================
// ─── app/Http/Resources/QuestionResource.php ────────────────
// ============================================================

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'type'             => $this->type->value,
            'type_label'       => $this->type->label(),
            'difficulty'       => $this->difficulty->value,
            'status'           => $this->status->value,

            // Content
            'question_text'    => $this->question_text,
            'question_media'   => $this->question_media,

            // Scoring
            'marks'            => (float) $this->marks,
            'negative_marks'   => (float) $this->negative_marks,
            'time_limit_sec'   => $this->time_limit_sec,

            // Explanation
            'explanation'      => $this->explanation,
            'explanation_media' => $this->explanation_media,
            'solution_approach' => $this->solution_approach,

            // Classification
            'subject'          => $this->whenLoaded('subject', fn() => [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ]),
            'topic'            => $this->whenLoaded('topic', fn() => [
                'id' => $this->topic->id,
                'name' => $this->topic->name,
                'code' => $this->topic->code,
            ]),
            'tags'             => $this->whenLoaded('tags', fn() =>
                $this->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])
            ),

            // Type-specific data
            'options'          => $this->whenLoaded('options', fn() =>
                $this->options->map(fn($o) => [
                    'id'          => $o->id,
                    'option_text' => $o->option_text,
                    'option_media' => $o->option_media,
                    'is_correct'  => $o->is_correct,
                    'sort_order'  => $o->sort_order,
                    'explanation' => $o->explanation,
                ])
            ),
            'blanks'           => $this->whenLoaded('blanks', fn() =>
                $this->blanks->map(fn($b) => [
                    'blank_number'     => $b->blank_number,
                    'correct_answers'  => $b->correct_answers,
                    'is_case_sensitive' => $b->is_case_sensitive,
                ])
            ),
            'match_pairs'      => $this->whenLoaded('matchPairs', fn() =>
                $this->matchPairs->map(fn($m) => [
                    'id'            => $m->id,
                    'column_a_text' => $m->column_a_text,
                    'column_b_text' => $m->column_b_text,
                    'sort_order'    => $m->sort_order,
                ])
            ),
            'expected_answer'  => $this->whenLoaded('expectedAnswer', fn() => $this->expectedAnswer ? [
                'answer_text' => $this->expectedAnswer->answer_text,
                'keywords'    => $this->expectedAnswer->keywords,
                'min_words'   => $this->expectedAnswer->min_words,
                'max_words'   => $this->expectedAnswer->max_words,
                'rubric'      => $this->expectedAnswer->rubric,
            ] : null),

            // Metadata
            'language'         => $this->language,
            'source'           => $this->source,
            'external_id'      => $this->external_id,

            // Stats
            'stats'            => [
                'times_used'    => $this->times_used,
                'times_correct' => $this->times_correct,
                'times_incorrect' => $this->times_incorrect,
                'accuracy_rate' => $this->times_used > 0
                    ? round(($this->times_correct / $this->times_used) * 100, 1)
                    : null,
                'avg_time_sec'  => $this->avg_time_sec,
            ],

            'creator'          => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'audit_logs'       => $this->whenLoaded('auditLogs'),

            'created_at'       => $this->created_at->toISOString(),
            'updated_at'       => $this->updated_at->toISOString(),
        ];
    }
}

