<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Api\Teacher\DashboardController as TeacherDashboard;
use App\Http\Controllers\Api\Student\DashboardController as StudentDashboard;
use App\Http\Controllers\Api\Parents\DashboardController as ParentDashboard;

// Blog — Public
use App\Http\Controllers\Api\Blog\BlogController;
use App\Http\Controllers\Api\Blog\BlogCategoryController;
use App\Http\Controllers\Api\Blog\BlogTagController;
use App\Http\Controllers\Api\Blog\BlogCommentController;

// Blog — Admin
use App\Http\Controllers\Api\Admin\BlogController          as AdminBlogController;
use App\Http\Controllers\Api\Admin\BlogCategoryController  as AdminBlogCategoryController;
use App\Http\Controllers\Api\Admin\BlogTagController       as AdminBlogTagController;
use App\Http\Controllers\Api\Admin\BlogCommentController   as AdminBlogCommentController;


use App\Http\Controllers\Api\V1\{
    SubjectController,
    TopicController,
    TagController,
    QuestionController,
    QuestionImportController,
    QuestionStatsController,
};


use App\Http\Controllers\Api\V1\{
    QuizCategoryController,
    QuizController,
    QuizAttemptController,
    QuizReportController,
};

use App\Http\Controllers\Api\V1\ExamSectionController;

use App\Http\Controllers\Api\V1\PracticeSetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
// ==================
// PUBLIC ROUTES
// ==================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Google OAuth
Route::prefix('auth/google')->group(function () {
    Route::get('/redirect',  [SocialAuthController::class, 'redirectToGoogle']);
    Route::post('/callback', [SocialAuthController::class, 'handleGoogleCallback']);
    Route::post('/token',    [SocialAuthController::class, 'handleGoogleToken']);
});

// Contact form — public
Route::post('/contact', [ContactController::class, 'store']);
Route::post('/v1/contact', [ContactController::class, 'store']);

// Home page sections — public (both /api/home and /api/v1/home)
Route::prefix('home')->group(function () {
    Route::get('/',               [HomeController::class, 'index']);
    Route::get('/practice-sets',  [HomeController::class, 'practiceSets']);
    Route::get('/exams',          [HomeController::class, 'exams']);
});
Route::prefix('v1/home')->group(function () {
    Route::get('/',               [HomeController::class, 'index']);
    Route::get('/practice-sets',  [HomeController::class, 'practiceSets']);
    Route::get('/exams',          [HomeController::class, 'exams']);
});

// Plans — public listing (both /api/plans and /api/v1/plans)
Route::get('/plans',           [PlanController::class, 'index']);
Route::get('/plans/{plan}',    [PlanController::class, 'show']);
Route::get('/v1/plans',        [PlanController::class, 'index']);
Route::get('/v1/plans/{plan}', [PlanController::class, 'show']);

// ==================
// PUBLIC BLOG ROUTES
// ==================
Route::prefix('v1')->group(function () {
    Route::prefix('blogs')->group(function () {
        Route::get('/',                      [BlogController::class, 'index']);
        Route::get('/{slug}/related',        [BlogController::class, 'related']);
        Route::get('/{slug}',                [BlogController::class, 'show']);
        Route::get('/{slug}/comments',       [BlogCommentController::class, 'index']);
        Route::post('/{slug}/comments',      [BlogCommentController::class, 'store']);
    });

    Route::prefix('blog-categories')->group(function () {
        Route::get('/',        [BlogCategoryController::class, 'index']);
        Route::get('/{slug}',  [BlogCategoryController::class, 'show']);
    });

    Route::prefix('blog-tags')->group(function () {
        Route::get('/',        [BlogTagController::class, 'index']);
        Route::get('/{slug}',  [BlogTagController::class, 'show']);
    });

    // Quiz categories — public read
    Route::get('quiz-categories',                    [QuizCategoryController::class, 'index']);
    Route::get('quiz-categories/{quiz_category}',    [QuizCategoryController::class, 'show']);

    // Exam sections — public read
    Route::get('exam-sections',                          [ExamSectionController::class, 'index']);
    Route::get('exam-sections/types',                    [ExamSectionController::class, 'types']);
    Route::get('exam-sections/{examSection}',            [ExamSectionController::class, 'show']);
    Route::get('exam-sections/{examSection}/tree',       [ExamSectionController::class, 'tree']);
    Route::get('exam-sections/{examSection}/content',    [ExamSectionController::class, 'content']);
    Route::get('exam-sections/{examSection}/breadcrumb', [ExamSectionController::class, 'breadcrumb']);

    // Quizzes — public read
    Route::get('quizzes',                      [QuizController::class, 'index']);
    Route::get('quizzes/{quiz}',               [QuizController::class, 'show']);
    Route::get('quizzes/{quiz}/leaderboard',   [QuizController::class, 'leaderboard']);

    // Practice sets — public read
    Route::get('practice-sets',                [PracticeSetController::class, 'index']);
    Route::get('practice-sets/{practiceSet}',  [PracticeSetController::class, 'show']);

    // Subjects & Topics — public read (for filters/dropdowns)
    Route::get('subjects',                     [SubjectController::class, 'index']);
    Route::get('subjects/{subject}',           [SubjectController::class, 'show']);
    Route::get('subjects/{subject}/topics',    [TopicController::class, 'index']);
    Route::get('topics/{topic}',               [TopicController::class, 'show']);

    // Tags — public read
    Route::get('tags',                         [TagController::class, 'index']);

    // Quiz schedules — public read
    Route::get('quizzes/{quiz}/schedules',     [QuizController::class, 'schedules']);
});

// ==================
// PROTECTED ROUTES (need token)
// ==================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user',    [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ── Profile ──────────────────────────────────────────────────────────
    Route::prefix('profile')->group(function () {
        Route::get('/',             [ProfileController::class, 'show']);
        Route::put('/',             [ProfileController::class, 'update']);
        Route::post('/avatar',      [ProfileController::class, 'uploadAvatar']);
        Route::delete('/avatar',    [ProfileController::class, 'removeAvatar']);
    });

    // ── My subscription (any authenticated user) ─────────────────────
    Route::get('/my/subscription',          [SubscriptionController::class, 'myCurrent']);
    Route::get('/my/subscriptions',         [SubscriptionController::class, 'myHistory']);
    Route::post('/my/subscription/cancel',  [SubscriptionController::class, 'cancel']);

    // ── Subscribe to a plan ───────────────────────────────────────────
    Route::post('/plans/{plan}/subscribe',  [SubscriptionController::class, 'subscribe']);

    // ==================
    // SUPERADMIN ROUTES
    // ==================
    Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
        Route::get('/dashboard',              [SuperAdminDashboard::class, 'index']);
        Route::get('/users',                  [SuperAdminDashboard::class, 'users']);
        Route::patch('/users/{user}/role',    [SuperAdminDashboard::class, 'updateUserRole']);
    });

    // ==================
    // ADMIN ROUTES
    // ==================
    Route::middleware('role:admin,superadmin')->prefix('admin')->group(function () {
        Route::get('/dashboard',  [AdminDashboard::class, 'index']);
        Route::get('/users',      [AdminDashboard::class, 'users']);
        Route::post('/users',     [AdminDashboard::class, 'createUser']);

        // ── Blog Management ───────────────────────────────────────────────
        Route::prefix('blogs')->group(function () {
            Route::get('/',                [AdminBlogController::class, 'index']);
            Route::post('/',               [AdminBlogController::class, 'store']);
            Route::get('/{id}',            [AdminBlogController::class, 'show']);
            Route::put('/{id}',            [AdminBlogController::class, 'update']);
            Route::delete('/{id}',         [AdminBlogController::class, 'destroy']);
            Route::post('/{id}/restore',   [AdminBlogController::class, 'restore']);
            Route::delete('/{id}/force',   [AdminBlogController::class, 'forceDelete']);
            Route::patch('/{id}/status',   [AdminBlogController::class, 'updateStatus']);
            Route::patch('/{id}/featured', [AdminBlogController::class, 'toggleFeatured']);
        });

        // ── Blog Category Management ──────────────────────────────────────
        Route::prefix('blog-categories')->group(function () {
            Route::get('/',        [AdminBlogCategoryController::class, 'index']);
            Route::post('/',       [AdminBlogCategoryController::class, 'store']);
            Route::get('/{id}',    [AdminBlogCategoryController::class, 'show']);
            Route::put('/{id}',    [AdminBlogCategoryController::class, 'update']);
            Route::delete('/{id}', [AdminBlogCategoryController::class, 'destroy']);
        });

        // ── Blog Tag Management ───────────────────────────────────────────
        Route::prefix('blog-tags')->group(function () {
            Route::get('/',        [AdminBlogTagController::class, 'index']);
            Route::post('/',       [AdminBlogTagController::class, 'store']);
            Route::get('/{id}',    [AdminBlogTagController::class, 'show']);
            Route::put('/{id}',    [AdminBlogTagController::class, 'update']);
            Route::delete('/{id}', [AdminBlogTagController::class, 'destroy']);
        });

        // ── Blog Comment Moderation ───────────────────────────────────────
        Route::prefix('blog-comments')->group(function () {
            Route::get('/',                    [AdminBlogCommentController::class, 'index']);
            Route::post('/bulk-status',        [AdminBlogCommentController::class, 'bulkUpdateStatus']);
            Route::get('/{id}',                [AdminBlogCommentController::class, 'show']);
            Route::patch('/{id}/status',       [AdminBlogCommentController::class, 'updateStatus']);
            Route::delete('/{id}',             [AdminBlogCommentController::class, 'destroy']);
        });

        // ── Plans ─────────────────────────────────────────────────────────
        Route::prefix('plans')->group(function () {
            Route::get('/',            [PlanController::class, 'adminIndex']);
            Route::post('/',           [PlanController::class, 'store']);
            Route::put('/{plan}',      [PlanController::class, 'update']);
            Route::delete('/{plan}',   [PlanController::class, 'destroy']);
        });

        // ── Subscriptions ─────────────────────────────────────────────────
        Route::prefix('subscriptions')->group(function () {
            Route::get('/',                               [SubscriptionController::class, 'adminIndex']);
            Route::post('/',                              [SubscriptionController::class, 'adminStore']);
            Route::patch('/{subscription}/status',        [SubscriptionController::class, 'updateStatus']);
            Route::patch('/{subscription}/extend',        [SubscriptionController::class, 'extend']);
        });

        // ── Contact Submissions ───────────────────────────────────────────
        Route::prefix('contact-submissions')->group(function () {
            Route::get('/',                              [ContactController::class, 'index']);
            Route::get('/{contactSubmission}',           [ContactController::class, 'show']);
            Route::patch('/{contactSubmission}/status',  [ContactController::class, 'updateStatus']);
            Route::delete('/{contactSubmission}',        [ContactController::class, 'destroy']);
        });
    });

    // ==================
    // TEACHER ROUTES
    // ==================
    Route::middleware('role:teacher,admin,superadmin')->prefix('teacher')->group(function () {
        Route::get('/dashboard', [TeacherDashboard::class, 'index']);
        Route::get('/students',  [TeacherDashboard::class, 'students']);
    });

    // ==================
    // STUDENT ROUTES
    // ==================
    Route::middleware('role:student')->prefix('student')->group(function () {
        Route::get('/dashboard', [StudentDashboard::class, 'index']);
    });

    // ==================
    // PARENT ROUTES
    // ==================
    Route::middleware('role:parent')->prefix('parent')->group(function () {
        Route::get('/dashboard', [ParentDashboard::class, 'index']);
    });
});




Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ── Exam Sections (Flexible Taxonomy) ──
    Route::get('exam-sections/types',                          [ExamSectionController::class, 'types']);
    Route::post('exam-sections/bulk-create',                   [ExamSectionController::class, 'bulkCreate']);
    Route::apiResource('exam-sections', ExamSectionController::class);
    Route::get('exam-sections/{examSection}/tree',             [ExamSectionController::class, 'tree']);
    Route::get('exam-sections/{examSection}/content',          [ExamSectionController::class, 'content']);
    Route::get('exam-sections/{examSection}/breadcrumb',       [ExamSectionController::class, 'breadcrumb']);
    Route::post('exam-sections/{examSection}/link',            [ExamSectionController::class, 'link']);
    Route::delete('exam-sections/{examSection}/unlink',        [ExamSectionController::class, 'unlink']);

    // Taxonomy
    Route::apiResource('subjects', SubjectController::class);
    Route::get('subjects/{subject}/topics', [TopicController::class, 'index']);
    Route::apiResource('topics', TopicController::class)->except(['index']);
    Route::apiResource('tags', TagController::class)->only(['index', 'store', 'destroy']);

    // Questions
    Route::apiResource('questions', QuestionController::class);
    Route::post('questions/{question}/clone', [QuestionController::class, 'clone']);
    Route::post('questions/{question}/submit-review', [QuestionController::class, 'submitReview']);
    Route::post('questions/{question}/approve', [QuestionController::class, 'approve']);
    Route::post('questions/{question}/reject', [QuestionController::class, 'reject']);
    Route::patch('questions/bulk-status', [QuestionController::class, 'bulkStatus']);

    // Import
    Route::post('questions/import', [QuestionImportController::class, 'upload']);
    Route::get('questions/import/template', [QuestionImportController::class, 'downloadTemplate']);
    Route::get('questions/import/{batch}/status', [QuestionImportController::class, 'status']);
    Route::get('questions/import/{batch}/errors', [QuestionImportController::class, 'errors']);
    Route::get('questions/import/batches', [QuestionImportController::class, 'batches']);
    Route::delete('questions/import/{batch}', [QuestionImportController::class, 'rollback']);

    // Stats & Search
    Route::get('questions-stats', [QuestionStatsController::class, 'index']);
    Route::get('questions-stats/aggregations', [QuestionStatsController::class, 'aggregations']);
    Route::post('questions-search', [QuestionStatsController::class, 'advancedSearch']);


    // ── Quiz Categories ──
    Route::apiResource('quiz-categories', QuizCategoryController::class);

    // ── Quizzes (Admin CRUD) ──
    Route::apiResource('quizzes', QuizController::class);
    Route::post('quizzes/{quiz}/publish', [QuizController::class, 'publish']);
    Route::post('quizzes/{quiz}/archive', [QuizController::class, 'archive']);
    Route::post('quizzes/{quiz}/duplicate', [QuizController::class, 'duplicate']);

    // ── Quiz Questions Management ──
    Route::get('quizzes/{quiz}/questions', [QuizController::class, 'questions']);
    Route::post('quizzes/{quiz}/questions/sync', [QuizController::class, 'syncQuestions']);

    // ── Quiz Schedules ──
    Route::get('quizzes/{quiz}/schedules', [QuizController::class, 'schedules']);
    Route::post('quizzes/{quiz}/schedules/sync', [QuizController::class, 'syncSchedules']);

    // ── Student: Attempt Flow ──
    Route::get('quizzes/{quiz}/check-access', [QuizAttemptController::class, 'checkAccess']);
    Route::post('quizzes/{quiz}/start', [QuizAttemptController::class, 'start']);
    Route::get('attempts/{attempt}', [QuizAttemptController::class, 'show']);
    Route::post('attempts/{attempt}/answer', [QuizAttemptController::class, 'saveAnswer']);
    Route::post('attempts/{attempt}/submit', [QuizAttemptController::class, 'submit']);
    Route::get('attempts/{attempt}/result', [QuizAttemptController::class, 'result']);
    Route::get('attempts/{attempt}/report',     [QuizReportController::class, 'show']);
    Route::get('attempts/{attempt}/report/pdf', [QuizReportController::class, 'downloadPdf']);

    // ── Leaderboard ──
    Route::get('quizzes/{quiz}/leaderboard', [QuizController::class, 'leaderboard']);

    // ── My Attempts (student dashboard) ──
    Route::get('my/attempts', [QuizAttemptController::class, 'myAttempts']);
    Route::get('my/quizzes', [QuizAttemptController::class, 'myQuizzes']);




    // ── Practice Sets (Admin) ──
    Route::apiResource('practice-sets', PracticeSetController::class);
    Route::post('practice-sets/{practiceSet}/publish', [PracticeSetController::class, 'publish']);
    Route::get('practice-sets/{practiceSet}/questions', [PracticeSetController::class, 'questions']);

    // ── Practice Sets (Student) ──
    Route::get('practice-sets/{practiceSet}/start', [PracticeSetController::class, 'start']);
    Route::post('practice-sets/{practiceSet}/check-answer', [PracticeSetController::class, 'checkAnswer']);
    Route::get('practice-sets/{practiceSet}/progress', [PracticeSetController::class, 'progress']);

    // ── Reward Points ──
    Route::get('my/reward-points', [PracticeSetController::class, 'myRewardPoints']);



});

