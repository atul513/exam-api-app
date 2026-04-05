<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{PracticeSet, Quiz};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * GET /api/home/practice-sets
     * Public listing of published practice sets for the home page.
     */
    public function practiceSets(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 8), 20);

        $sets = PracticeSet::published()
            ->with(['category:id,name', 'subject:id,name,code'])
            ->select([
                'id', 'title', 'slug', 'description', 'thumbnail_url',
                'access_type', 'price',
                'category_id', 'subject_id', 'topic_id',
                'total_questions', 'allow_reward_points',
                'created_at',
            ])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $sets,
        ]);
    }

    /**
     * GET /api/home/exams
     * Public listing of published exams for the home page.
     */
    public function exams(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 8), 20);

        $exams = Quiz::published()
            ->public()
            ->ofType('exam')
            ->with(['category:id,name'])
            ->select([
                'id', 'title', 'slug', 'description', 'thumbnail_url',
                'access_type', 'price',
                'category_id',
                'total_questions', 'total_marks',
                'total_duration_min', 'duration_mode',
                'negative_marking', 'pass_percentage',
                'max_attempts',
                'created_at',
            ])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $exams,
        ]);
    }

    /**
     * GET /api/home
     * Combined home page data: both practice sets and exams in one request.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 6), 20);

        $practiceSets = PracticeSet::published()
            ->with(['category:id,name', 'subject:id,name,code'])
            ->select([
                'id', 'title', 'slug', 'description', 'thumbnail_url',
                'access_type', 'price',
                'category_id', 'subject_id',
                'total_questions', 'allow_reward_points',
                'created_at',
            ])
            ->latest()
            ->limit($limit)
            ->get();

        $exams = Quiz::published()
            ->public()
            ->ofType('exam')
            ->with(['category:id,name'])
            ->select([
                'id', 'title', 'slug', 'description', 'thumbnail_url',
                'access_type', 'price',
                'category_id',
                'total_questions', 'total_marks',
                'total_duration_min', 'duration_mode',
                'negative_marking', 'pass_percentage',
                'created_at',
            ])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => [
                'practice_sets' => $practiceSets,
                'exams'         => $exams,
            ],
        ]);
    }
}
