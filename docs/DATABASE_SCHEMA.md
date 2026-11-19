# Database Schema

MySQL 8, InnoDB, `utf8mb4_unicode_ci`. Every table has `id BIGINT UNSIGNED` as its primary key and `created_at`/`updated_at` columns unless noted. Foreign keys are explicit and cascade only where a child has no meaning without its parent.

## Normalization notes (changes from the initial entity list)

| Initial suggestion | Decision |
|---|---|
| `course_categories` + `skills` | Split into **`domains`** (Networking, Linux, … the 8 assessment domains) and **`skills`** (finer-grained, each in one domain). Courses, questions, careers and topics all reference `domains`, so one taxonomy drives the whole personalization flow. |
| `learning_path_nodes` holding lessons | Paths hold **topics**. Lessons belong to topics through `topic_lessons`, so a lesson can appear in several careers' roadmaps without copying rows. |
| `learning_path_progress` | **Dropped.** Node status is computed from `lesson_progress` and topic prerequisites ([ARCHITECTURE §6.3](ARCHITECTURE.md#63-node-status-computed-on-read-never-stored)). |
| `lesson_contents` | Becomes **`lesson_sections`**: ordered, typed blocks (`prose`, `callout`, `diagram`, `steps`, `visual_3d`, `code`) with a JSON payload. |
| `user_favorites` | Becomes **`course_favorites`**, since only courses are favorited. |
| `notifications` | Uses Laravel's standard **`notifications`** table, plus **`notification_preferences`**. |
| Assessment statistics | Only `score_percent` is kept on a submitted attempt, as an immutable snapshot. Domain breakdowns are computed from `assessment_answers`. |
| Labs | Added `labs` and `lab_attempts`. |

## ERD

```mermaid
erDiagram
  users ||--o| user_profiles : has
  users ||--o| learning_goals : has
  learning_goals }o--o| careers : targets
  learning_goals ||--o{ learning_goal_domains : "interested in"
  domains ||--o{ skills : contains

  careers ||--o{ career_domains : requires
  domains ||--o{ career_domains : ""
  careers ||--o{ career_skills : requires
  skills ||--o{ career_skills : ""
  careers ||--o{ career_topics : "roadmap template"
  topics ||--o{ career_topics : ""

  domains ||--o{ topics : groups
  topics ||--o{ topic_prerequisites : "depends on"
  topics ||--o{ topic_lessons : contains
  lessons ||--o{ topic_lessons : ""

  domains ||--o{ courses : categorizes
  courses ||--o{ course_chapters : has
  course_chapters ||--o{ lessons : has
  courses ||--o{ course_skills : teaches
  courses ||--o{ course_prerequisites : "requires"
  lessons ||--o{ lesson_sections : has
  lessons ||--o| exercises : has
  exercises ||--o{ exercise_questions : has

  users ||--o{ enrollments : ""
  courses ||--o{ enrollments : ""
  users ||--o{ course_favorites : ""
  users ||--o{ lesson_progress : ""
  lessons ||--o{ lesson_progress : ""
  users ||--o{ exercise_attempts : ""
  exercise_attempts ||--o{ exercise_attempt_answers : ""

  assessments ||--o{ assessment_questions : has
  domains ||--o{ assessment_questions : ""
  assessment_questions ||--o{ assessment_options : has
  users ||--o{ assessment_attempts : ""
  assessment_attempts ||--o{ assessment_answers : ""

  users ||--o{ learning_paths : ""
  careers ||--o{ learning_paths : ""
  learning_paths ||--o{ learning_path_nodes : ""
  topics ||--o{ learning_path_nodes : ""

  labs }o--o| lessons : "attached to"
  users ||--o{ lab_attempts : ""
  users ||--o{ learning_activities : ""
  users ||--o{ notification_preferences : ""
```

## Tables

### Identity

**users**: `name`, `email` (unique), `email_verified_at`, `password`, `role` enum(`learner`,`admin`) default learner, `remember_token`.
Plus the Laravel defaults `password_reset_tokens` and `sessions`.

**user_profiles** (✅ Phase 2): `user_id` (unique FK, cascade), `avatar_path` null, `phone` null, `date_of_birth` date null, `education_level` enum(`high_school`,`undergraduate`,`graduate`,`other`) null, `locale` enum(`en`,`vi`) default `en`, `theme` enum(`light`,`dark`,`system`) default `system`.

**learning_goals** (Phase 4): `user_id` (unique FK), `career_id` FK null, `self_level` enum(`beginner`,`intermediate`,`advanced`), `weekly_minutes` smallint.
**learning_goal_domains**: `learning_goal_id`, `domain_id`. The primary key is the pair (areas of interest).

### Taxonomy

**domains**: `slug` (unique), `name`, `description`, `position`.
**skills**: `domain_id` FK, `slug` (unique), `name`.

### Careers

**careers**: `slug` (unique), `title`, `summary`, `description`, `responsibilities` JSON (an ordered list of strings), `is_published`.
**career_domains**: `career_id`, `domain_id`, `required_level` tinyint (1 to 3). Primary key is the pair.
**career_skills**: `career_id`, `skill_id`. Primary key is the pair.
**career_topics**: `career_id`, `topic_id`, `stage` tinyint (milestone grouping), `position`. Unique (`career_id`,`topic_id`).

### Roadmap content

**topics**: `domain_id` FK, `slug` (unique), `title`, `summary`, `level` tinyint (1 to 3).
**topic_prerequisites**: `topic_id`, `prerequisite_topic_id`. Primary key is the pair, with a check constraint against self-reference. Cycles are rejected in the seeder and validator.
**topic_lessons**: `topic_id`, `lesson_id`, `position`, `is_required` bool. Primary key is the pair.

### Courses and lessons

**courses**: `domain_id` FK, `slug` (unique), `title`, `summary`, `description`, `difficulty` tinyint (1 to 3), `thumbnail_path`, `objectives` JSON, `is_published`, `published_at`. Indexes: (`domain_id`,`difficulty`), and FULLTEXT(`title`,`summary`) for search.
`estimated_minutes` and `lesson_count` are **not stored**. They are aggregated with `withSum`/`withCount` in queries.
**course_prerequisites**: `course_id`, `prerequisite_course_id`.
**course_skills**: `course_id`, `skill_id`.
**course_chapters**: `course_id` FK cascade, `title`, `position`.
**lessons**: `course_chapter_id` FK cascade, `slug` (unique), `title`, `summary`, `objectives` JSON, `key_takeaways` JSON, `resources` JSON (a list of {label, url}), `estimated_minutes` smallint, `position`.
**lesson_sections**: `lesson_id` FK cascade, `position`, `type` enum(`prose`,`callout`,`diagram`,`steps`,`code`,`visual_3d`), `heading` null, `payload` JSON. Index (`lesson_id`,`position`).

### Exercises

**exercises**: `lesson_id` (unique FK, cascade), `pass_percent` tinyint default 70.
**exercise_questions**: `exercise_id` FK cascade, `position`, `type` enum(`single_choice`,`multi_choice`,`true_false`,`fill_blank`,`matching`,`ordering`,`scenario`), `prompt` text, `payload` JSON (options, pairs, items: what the learner sees), `answer` JSON (**never serialized before submission**), `explanation` text, `lesson_section_id` FK null (the theory reference).
**exercise_attempts**: `user_id`, `exercise_id`, `score_percent`, `passed` bool, `submitted_at`. Index (`user_id`,`exercise_id`,`passed`).
**exercise_attempt_answers**: `exercise_attempt_id` FK cascade, `exercise_question_id`, `response` JSON, `is_correct` bool.

### Assessment

**assessments**: `slug` (unique), `title`, `description`, `is_active`, `time_limit_minutes` null.
**assessment_questions**: `assessment_id` FK, `domain_id` FK, `difficulty` tinyint (1 to 3), `prompt`, `explanation`, `position`. Index (`assessment_id`,`domain_id`).
**assessment_options**: `assessment_question_id` FK cascade, `label`, `is_correct` bool, `position`.
**assessment_attempts**: `user_id`, `assessment_id`, `started_at`, `submitted_at` null, `score_percent` null. Index (`user_id`,`submitted_at`). Multiple attempts are allowed.
**assessment_answers**: `assessment_attempt_id` FK cascade, `assessment_question_id`, `assessment_option_id` null, `is_correct` bool null (set on submit). Unique (`attempt_id`,`question_id`), so saving an answer is an upsert.

### Personalized paths

**learning_paths**: `user_id`, `career_id`, `assessment_attempt_id` null, `version` smallint, `status` enum(`active`,`archived`), `generated_at`. Only one `active` path per user, enforced in the generator action inside a transaction.
**learning_path_nodes**: `learning_path_id` FK cascade, `topic_id`, `stage`, `position`, `recommendation` enum(`required`,`review`), `reason_key` + `reason_params` JSON (rendered through i18n). Unique (`learning_path_id`,`topic_id`).

### Progress and engagement

**enrollments**: `user_id`, `course_id`, `enrolled_at`, `completed_at` null (an event timestamp, set once). Unique pair.
**course_favorites**: `user_id`, `course_id`, `created_at`. Unique pair.
**lesson_progress**: `user_id`, `lesson_id`, `status` enum(`started`,`completed`), `started_at`, `completed_at` null, `time_spent_seconds` int. Unique pair. Index (`user_id`,`completed_at`).
**learning_activities**: `user_id`, `type` enum(`lesson_started`,`lesson_completed`,`exercise_submitted`,`assessment_submitted`,`lab_completed`,`study_session`), `subject_type`/`subject_id` (morph), `duration_seconds` int default 0, `occurred_at`. Index (`user_id`,`occurred_at`). This is an append-only source for streaks, weekly time and the recent-activity feed.

### Labs

**labs**: `slug` (unique), `title`, `summary`, `domain_id`, `lesson_id` FK null, `engine` varchar (`linux-shell`, `firewall-rules`, `http-inspector`, `packet-flow`), `provider` varchar default `browser-sim`, `scenario` JSON, `difficulty`.
**lab_attempts**: `user_id`, `lab_id`, `actions` JSON, `checkpoints_passed` JSON, `completed_at` null.

### Notifications

**notifications**: Laravel standard (uuid `id`, `type`, `notifiable_*`, `data` JSON, `read_at`).
**notification_preferences**: `user_id`, `type` enum(`recommendation`,`lesson_completed`,`milestone`,`reminder`,`assessment_result`), `in_app` bool, `email` bool. Unique (`user_id`,`type`).

## Transactions

| Operation | Rows written atomically |
|---|---|
| Register | users, user_profiles, notification_preferences defaults |
| Submit assessment | answers scored, attempt score + submitted_at, activity |
| Generate roadmap | archive old path, insert path + nodes |
| Submit exercise | attempt, attempt answers, lesson_progress completion when passed, activity |
| Complete lesson | lesson_progress, enrollment.completed_at when the course is done, activity |

Notifications are dispatched **after commit** (`afterCommit` listeners).

## Seed data

`DatabaseSeeder` runs, in order: domains → skills → careers (7 roles) → topics + prerequisites + career_topics → courses → chapters → lessons + sections → exercises → the assessment (about 40 questions, 5 per domain, mixed difficulty) → labs → a demo learner with progress (`demo@cybercompass.test`, password from `.env`, never committed). Content lives in `database/seeders/content/*.php` (or JSON) files so it's reviewable separately from the seeder code.
