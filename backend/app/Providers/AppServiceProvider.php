<?php

namespace App\Providers;

use App\Models\AssessmentAttempt;
use App\Models\ExerciseAttempt;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Admin\Policies\UserAdminPolicy;
use App\Modules\Assessment\Events\AssessmentSubmitted;
use App\Modules\Assessment\Policies\AssessmentAttemptPolicy;
use App\Modules\Content\Blocks\BlockRegistry;
use App\Modules\Content\Events\LessonPublished;
use App\Modules\Content\Policies\LessonPolicy;
use App\Modules\Labs\Engines\LabLimits;
use App\Modules\Labs\Events\LabCompleted;
use App\Modules\Learning\Events\ExerciseSubmitted;
use App\Modules\Learning\Events\LessonCompleted;
use App\Modules\Learning\Events\LessonStarted;
use App\Modules\Notifications\Listeners\NotifyLearner;
use App\Modules\Progress\Events\MilestoneReached;
use App\Modules\Progress\Listeners\CheckMilestones;
use App\Modules\Progress\Listeners\RecordLearningActivity;
use App\Support\Validation\MaxBytes;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The single source of lesson block types (ARCHITECTURE D17).
        $this->app->singleton(BlockRegistry::class, fn () => BlockRegistry::default());
    }

    public function boot(): void
    {
        // Fail loudly in development on lazy loading (N+1), silent attribute drops and missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        // bcrypt reads only the first 72 bytes, so longer passwords are refused (MaxBytes).
        Password::defaults(fn () => Password::min(8)->max(128)->letters()->numbers()->rules([new MaxBytes(72)]));

        // Password reset emails link to the frontend, which posts the token back to the API.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));

        // Admin console: account management and content authoring (one admin role today, assumption A1).
        Gate::policy(User::class, UserAdminPolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);

        // Learners' own records: another learner's attempt answers 404.
        Gate::policy(AssessmentAttempt::class, AssessmentAttemptPolicy::class);

        $this->configureRateLimits();
        $this->configureLearningEvents();
    }

    /** Activity recording (D33), milestones and notifications (D34): listeners on the learning events, and short morph names for activity subjects. */
    private function configureLearningEvents(): void
    {
        Relation::morphMap([
            'lesson' => Lesson::class,
            'lab' => Lab::class,
            'exercise_attempt' => ExerciseAttempt::class,
            'assessment_attempt' => AssessmentAttempt::class,
        ]);

        Event::listen(LessonStarted::class, [RecordLearningActivity::class, 'lessonStarted']);
        Event::listen(LessonCompleted::class, [RecordLearningActivity::class, 'lessonCompleted']);
        Event::listen(ExerciseSubmitted::class, [RecordLearningActivity::class, 'exerciseSubmitted']);
        Event::listen(AssessmentSubmitted::class, [RecordLearningActivity::class, 'assessmentSubmitted']);
        Event::listen(LabCompleted::class, [RecordLearningActivity::class, 'labCompleted']);

        // Milestones once the activity is stored, then notifications (D34).
        foreach ([LessonStarted::class, LessonCompleted::class, ExerciseSubmitted::class, AssessmentSubmitted::class, LabCompleted::class] as $event) {
            Event::listen($event, CheckMilestones::class);
        }
        Event::listen(LessonCompleted::class, [NotifyLearner::class, 'lessonCompleted']);
        Event::listen(AssessmentSubmitted::class, [NotifyLearner::class, 'assessmentSubmitted']);
        Event::listen(MilestoneReached::class, [NotifyLearner::class, 'milestoneReached']);
        Event::listen(LessonPublished::class, [NotifyLearner::class, 'lessonPublished']);
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Public reads by the Next.js server (no session) all come from its own address; abuse
            // of public pages is limited at the edge instead (SECURITY_REVIEW.md S8, D50).
            $token = (string) config('cybercompass.internal_api_token');
            if ($token !== '' && ! $request->user() && hash_equals($token, (string) $request->header('X-Internal-Token'))) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        // Login has its own per-email lockout in AttemptLogin; these cover the other auth endpoints.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by('login:'.$request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(5)
            ->by(($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip()));
        // Admin API: per admin, on top of the general api limit (docs/PHASE_3_5_CONTENT_AND_ADMIN.md §8).
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(120)
            ->by('admin:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
        // Lab actions run a server-side engine each (ARCHITECTURE §7.1).
        RateLimiter::for('lab-actions', fn (Request $request) => Limit::perMinute(LabLimits::ACTIONS_PER_MINUTE)
            ->by('lab:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));
        // Header search runs while typing (debounced); generous, but bounded per user or address.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)
            ->by('search:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));
        // Exercise attempts are scored and stored on every submit; retries are fine, a loop is not.
        RateLimiter::for('exercise-attempts', fn (Request $request) => Limit::perMinute(20)
            ->by('exercise:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));
    }
}
