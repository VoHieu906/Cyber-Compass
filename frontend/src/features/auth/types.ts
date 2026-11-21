import type { Locale } from "@/i18n/config";

export type ThemePreference = "light" | "dark" | "system";
export type EducationLevel = "high_school" | "undergraduate" | "graduate" | "other";

/** The signed-in user, as returned by GET /api/v1/me (backend UserResource). */
export type User = {
  id: number;
  name: string;
  email: string;
  role: "learner" | "admin";
  email_verified_at: string | null;
  created_at: string | null;
  profile: {
    avatar_url: string | null;
    phone: string | null;
    date_of_birth: string | null;
    education_level: EducationLevel | null;
    locale: Locale;
    theme: ThemePreference;
    /** IANA timezone for local dates (streaks, weeks, reminders); null until the browser reports one. */
    timezone: string | null;
  };
  learning_goal: LearningGoal | null;
};

export type SelfLevel = "beginner" | "intermediate" | "advanced";

/** The learner's goal (PUT /api/v1/me/learning-goal). Careers and domains are named by slug. */
export type LearningGoal = {
  career: { slug: string; title: string } | null;
  self_level: SelfLevel | null;
  weekly_minutes: number | null;
  domains: string[];
};

export type LoginInput = { email: string; password: string; remember: boolean };
export type RegisterInput = { name: string; email: string; password: string; password_confirmation: string };
export type ResetPasswordInput = { token: string; email: string; password: string; password_confirmation: string };
