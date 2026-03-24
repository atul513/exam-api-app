<?php


// ============================================================
// ─── app/Http/Requests/StoreQuestionRequest.php ─────────────
// ============================================================

namespace App\Http\Requests;

use App\Enums\{QuestionType, Difficulty};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        $rules = [
            'subject_id'       => ['required', 'exists:subjects,id'],
            'topic_id'         => ['required', 'exists:topics,id'],
            'type'             => ['required', new Enum(QuestionType::class)],
            'difficulty'       => ['required', new Enum(Difficulty::class)],
            'question_text'    => ['required', 'string', 'max:10000'],
            'question_media'   => ['nullable', 'array'],
            'marks'            => ['required', 'numeric', 'min:0.01', 'max:999'],
            'negative_marks'   => ['nullable', 'numeric', 'min:0'],
            'time_limit_sec'   => ['nullable', 'integer', 'min:5', 'max:3600'],
            'explanation'      => ['nullable', 'string', 'max:20000'],
            'solution_approach' => ['nullable', 'string', 'max:20000'],
            'language'         => ['nullable', 'string', 'max:10'],
            'source'           => ['nullable', 'string', 'max:255'],
            'tags'             => ['nullable', 'array'],
            'tags.*'           => ['string', 'max:100'],
        ];

        // Type-specific validation
        $type = $this->input('type');

        if (in_array($type, ['mcq', 'multi_select', 'true_false'])) {
            $rules['options']              = ['required', 'array', 'min:2', 'max:8'];
            $rules['options.*.option_text'] = ['required', 'string', 'max:2000'];
            $rules['options.*.is_correct']  = ['required', 'boolean'];
            $rules['options.*.explanation']  = ['nullable', 'string', 'max:5000'];
        }

        if ($type === 'mcq') {
            // Exactly 1 correct
            $rules['options'] = ['required', 'array', 'min:2', 'max:6',
                function ($attr, $value, $fail) {
                    $correctCount = collect($value)->where('is_correct', true)->count();
                    if ($correctCount !== 1) {
                        $fail('MCQ must have exactly 1 correct answer.');
                    }
                }
            ];
        }

        if ($type === 'multi_select') {
            $rules['options'] = ['required', 'array', 'min:2', 'max:8',
                function ($attr, $value, $fail) {
                    $correctCount = collect($value)->where('is_correct', true)->count();
                    if ($correctCount < 2) {
                        $fail('Multi-select must have at least 2 correct answers.');
                    }
                }
            ];
        }

        if ($type === 'true_false') {
            $rules['options'] = ['required', 'array', 'size:2'];
        }

        if ($type === 'fill_blank') {
            $rules['blanks']                     = ['required', 'array', 'min:1'];
            $rules['blanks.*.blank_number']      = ['required', 'integer', 'min:1'];
            $rules['blanks.*.correct_answers']   = ['required', 'array', 'min:1'];
            $rules['blanks.*.correct_answers.*'] = ['required', 'string'];
            $rules['blanks.*.is_case_sensitive']  = ['nullable', 'boolean'];
        }

        if ($type === 'match_column') {
            $rules['match_pairs']                 = ['required', 'array', 'min:2', 'max:10'];
            $rules['match_pairs.*.column_a_text'] = ['required', 'string', 'max:1000'];
            $rules['match_pairs.*.column_b_text'] = ['required', 'string', 'max:1000'];
        }

        if (in_array($type, ['short_answer', 'long_answer'])) {
            $rules['expected_answer']               = ['required', 'array'];
            $rules['expected_answer.answer_text']    = ['required', 'string'];
            $rules['expected_answer.keywords']       = ['nullable', 'array'];
            $rules['expected_answer.keywords.*']     = ['string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'options.required'      => 'Options are required for this question type.',
            'blanks.required'       => 'Blanks definition is required for fill-in-the-blank questions.',
            'match_pairs.required'  => 'Match pairs are required for match-the-column questions.',
        ];
    }
}