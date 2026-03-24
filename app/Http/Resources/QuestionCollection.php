<?php

// ============================================================
// ─── app/Http/Resources/QuestionCollection.php ──────────────
// ============================================================

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class QuestionCollection extends ResourceCollection
{
    public $collects = QuestionResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'pagination' => [
                'current_page' => $paginated['current_page'],
                'per_page'     => $paginated['per_page'],
                'total'        => $paginated['total'],
                'total_pages'  => $paginated['last_page'],
            ],
        ];
    }
}