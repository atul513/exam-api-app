<?php

namespace App\Providers;


use App\Models\{Question, Subject, Topic};
use App\Observers\{QuestionObserver, SubjectObserver, TopicObserver};
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
         // ── Register Observers ──
        Question::observe(QuestionObserver::class);
        Subject::observe(SubjectObserver::class);
        Topic::observe(TopicObserver::class);

        // ── Performance: prevent lazy loading in dev ──
        Model::preventLazyLoading(!$this->app->isProduction());

        // ── Performance: prevent silently discarding attributes ──
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());
    }
}
