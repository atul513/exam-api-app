<?php

// ============================================================
// FILE: routes/console.php
// Laravel 12 registers artisan commands here using closures
// ============================================================

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\{Question, Subject, Topic, Tag, ImportBatch};
use App\Services\QuestionCacheService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DemoImportExport;

// ─────────────────────────────────────────────────────────────
// php artisan questionbank:flush-cache
// php artisan questionbank:flush-cache --type=subjects
// php artisan questionbank:flush-cache --type=topics --subject=1
// php artisan questionbank:flush-cache --type=questions --question=42
// ─────────────────────────────────────────────────────────────

Artisan::command(
    'questionbank:flush-cache
        {--type=all : Flush specific type: subjects, topics, tags, stats, questions, all}
        {--subject= : Subject ID (for flushing topics of a specific subject)}
        {--question= : Question ID (for flushing a specific question)}',
    function () {
        $type = $this->option('type');

        switch ($type) {
            case 'subjects':
                QuestionCacheService::flushSubjects();
                $this->info('✓ Subject cache flushed.');
                break;

            case 'topics':
                $subjectId = $this->option('subject');
                if (!$subjectId) {
                    $this->error('Please provide --subject=ID for topic cache flush.');
                    return 1;
                }
                QuestionCacheService::flushTopics((int) $subjectId);
                $this->info("✓ Topic cache flushed for subject #{$subjectId}.");
                break;

            case 'tags':
                QuestionCacheService::flushTags();
                $this->info('✓ Tag cache flushed.');
                break;

            case 'stats':
                QuestionCacheService::flushStats();
                $this->info('✓ Stats + aggregation cache flushed.');
                break;

            case 'questions':
                $questionId = $this->option('question');
                if ($questionId) {
                    QuestionCacheService::flushQuestion((int) $questionId);
                    $this->info("✓ Cache flushed for question #{$questionId}.");
                } else {
                    $this->info('Flushing all question caches...');
                    QuestionCacheService::flushAll();
                    $this->info('✓ All question caches flushed.');
                }
                break;

            case 'all':
            default:
                QuestionCacheService::flushAll();
                $this->info('✓ All question bank caches flushed.');
                break;
        }

        return 0;
    }
)->purpose('Flush question bank cache (all or specific type)');


// ─────────────────────────────────────────────────────────────
// php artisan questionbank:stats
// ─────────────────────────────────────────────────────────────

Artisan::command('questionbank:stats', function () {

    $this->newLine();
    $this->info('  QUESTION BANK — HEALTH CHECK');
    $this->info('  ============================');

    // Database counts
    $this->newLine();
    $this->info('  Database:');
    $this->table(
        ['Table', 'Count'],
        [
            ['questions',      Question::count()],
            ['subjects',       Subject::count()],
            ['topics',         Topic::count()],
            ['tags',           Tag::count()],
            ['import_batches', ImportBatch::count()],
        ]
    );

    // Questions by status
    $this->info('  Questions by status:');
    $statuses = Question::selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->pluck('count', 'status');
    $this->table(
        ['Status', 'Count'],
        $statuses->map(fn($c, $s) => [$s, $c])->values()
    );

    // Questions by type
    $this->info('  Questions by type:');
    $types = Question::selectRaw('type, COUNT(*) as count')
        ->groupBy('type')
        ->pluck('count', 'type');
    $this->table(
        ['Type', 'Count'],
        $types->map(fn($c, $t) => [$t, $c])->values()
    );

    // Cache status
    $this->newLine();
    $this->info('  Cache:');
    $driver  = config('cache.default');
    $enabled = config('questionbank.cache.enabled');
    $this->line("    Driver:  {$driver}");
    $this->line("    Enabled: " . ($enabled ? 'Yes' : 'No'));

    try {
        Cache::put('qbank:healthcheck', 'ok', 10);
        $val = Cache::get('qbank:healthcheck');
        Cache::forget('qbank:healthcheck');
        $this->line('    Status:  ' . ($val === 'ok' ? '✓ Working' : '✗ Read failed'));
    } catch (\Throwable $e) {
        $this->error("    Status:  ✗ Error — {$e->getMessage()}");
    }

    // MySQL indexes
    $this->newLine();
    $this->info('  MySQL indexes on questions table:');
    $indexes = DB::select('SHOW INDEX FROM questions');
    $names   = collect($indexes)->pluck('Key_name')->unique()->values();
    foreach ($names as $name) {
        $this->line("    ✓ {$name}");
    }

    $this->newLine();

    return 0;

})->purpose('Show question bank statistics and health check');


Artisan::command(
    'questionbank:generate-demo-excel {--subject= : Subject code} {--topic= : Topic code}',
    function () {
        $subjectCode = $this->option('subject');
        $topicCode = $this->option('topic');

        // Auto-detect first subject/topic if not provided
        if (!$subjectCode) {
            $subject = Subject::first();
            if (!$subject) {
                $this->error('No subjects found. Create a subject first: php artisan tinker → Subject::create([...])');
                return 1;
            }
            $subjectCode = $subject->code;
            $this->line("Using subject: {$subject->name} ({$subjectCode})");
        }

        if (!$topicCode) {
            $subject = Subject::where('code', $subjectCode)->first();
            $topic = Topic::where('subject_id', $subject?->id)->first();
            if (!$topic) {
                $this->error("No topics found for subject '{$subjectCode}'. Create a topic first.");
                return 1;
            }
            $topicCode = $topic->code;
            $this->line("Using topic: {$topic->name} ({$topicCode})");
        }

        $path = 'demo-import.xlsx';
        Excel::store(new DemoImportExport($subjectCode, $topicCode), $path, 'local');

        $fullPath = storage_path("app/{$path}");
        $this->info("Demo Excel created: {$fullPath}");
        $this->line('Upload it via: POST /api/v1/questions/import');

        return 0;
    }
)->purpose('Generate a demo Excel file for testing question import');
