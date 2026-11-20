<?php

namespace Database\Seeders;

use App\Enums\Locale;
use App\Enums\ThemePreference;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\User;
use App\Modules\Assessment\Actions\SaveAnswer;
use App\Modules\Assessment\Actions\StartAttempt;
use App\Modules\Assessment\Actions\SubmitAttempt;
use App\Modules\Learning\Actions\RecordLessonProgress;
use App\Modules\Profile\Actions\SaveLearningGoal;
use App\Modules\Roadmap\Actions\GenerateLearningPath;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for local development and demonstrations. Passwords come from
 * backend/.env and are never committed; an account whose variable is empty is skipped.
 *   demo@cybercompass.test   learner  DEMO_USER_PASSWORD
 *   admin@cybercompass.test  admin    DEMO_ADMIN_PASSWORD
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->account('demo@cybercompass.test', 'Demo Learner', UserRole::Learner, 'demo_user_password', 'DEMO_USER_PASSWORD');
        $this->account('admin@cybercompass.test', 'Demo Admin', UserRole::Admin, 'demo_admin_password', 'DEMO_ADMIN_PASSWORD');
    }

    private function account(string $email, string $name, UserRole $role, string $configKey, string $envName): void
    {
        $password = config("cybercompass.{$configKey}");

        if (blank($password)) {
            $this->command?->warn("{$envName} is not set; skipping {$email}.");

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );
        // Not fillable on purpose; seeders set them directly.
        $user->forceFill(['role' => $role, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $user->profile()->updateOrCreate([], [
            'locale' => Locale::English,
            'theme' => ThemePreference::System,
        ]);

        if ($role === UserRole::Learner) {
            $this->learningJourney($user);
        }
    }

    /**
     * Gives the demo learner something to show: a SOC Analyst goal, a submitted assessment with
     * strengths, developing domains and gaps, one finished lesson and a roadmap built from them.
     * Runs once: a learner who already has a roadmap is left alone, so re-seeding never
     * overwrites what was done in the app.
     */
    private function learningJourney(User $user): void
    {
        if ($user->learningPaths()->exists()) {
            return;
        }

        app(SaveLearningGoal::class)->handle($user, [
            'career' => 'soc-analyst',
            'self_level' => 'beginner',
            'weekly_minutes' => 240,
            'domains' => ['networking', 'security-fundamentals'],
        ]);

        $assessment = Assessment::query()->active()->first();
        if ($assessment && ! $user->assessmentAttempts()->submitted()->exists()) {
            $attempt = app(StartAttempt::class)($user, $assessment)['attempt'];
            // Which difficulties the demo learner answers correctly, per domain.
            $correct = [
                'networking' => [1, 2, 3], 'system-administration' => [1, 2, 3],   // strengths
                'security-fundamentals' => [1, 2], 'programming' => [1, 2], 'operating-systems' => [1],   // developing
                'linux' => [], 'web-security' => [], 'cryptography' => [],   // gaps (only the beginner questions right)
            ];
            foreach ($attempt->answers()->with('question.domain', 'question.options')->get() as $answer) {
                $question = $answer->question;
                $right = $question->difficulty === 1 || in_array($question->difficulty, $correct[$question->domain->slug] ?? [], true);
                $option = $question->options->first(fn ($o) => $o->is_correct === $right);
                app(SaveAnswer::class)($attempt, $question->id, $option?->id);
            }
            app(SubmitAttempt::class)($attempt);
        }

        $lesson = Lesson::query()->where('slug', 'cia-triad')->visibleToLearners()->first();
        if ($lesson) {
            app(RecordLessonProgress::class)->complete($user, $lesson, 25 * 60);
        }

        app(GenerateLearningPath::class)->handle($user);
    }
}
