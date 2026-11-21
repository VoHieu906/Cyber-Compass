import type { Locale } from "@/i18n/config";
import { api } from "@/lib/api/client";
import type { EducationLevel, SelfLevel, ThemePreference, User } from "@/features/auth/types";

export type ProfileInput = {
  name: string;
  phone: string | null;
  date_of_birth: string | null;
  education_level: EducationLevel | null;
};

/** `timezone` is an IANA name such as Asia/Ho_Chi_Minh (D33). */
export type PreferencesInput = Partial<{ locale: Locale; theme: ThemePreference; timezone: string }>;

export type PasswordInput = { current_password: string; password: string; password_confirmation: string };

export const updateProfile = (input: ProfileInput) => api.patch<User>("/api/v1/me/profile", input);

export function uploadAvatar(file: File): Promise<User> {
  const body = new FormData();
  body.append("avatar", file);
  return api.post<User>("/api/v1/me/avatar", body);
}

export const removeAvatar = () => api.delete<User>("/api/v1/me/avatar");

export const updatePreferences = (input: PreferencesInput) => api.patch<User>("/api/v1/me/preferences", input);

/** Only the fields given change; null clears one. */
export type LearningGoalInput = Partial<{ career: string | null; self_level: SelfLevel | null; weekly_minutes: number | null; domains: string[] }>;

export const updateLearningGoal = (input: LearningGoalInput) => api.put<User>("/api/v1/me/learning-goal", input);

export const changePassword = (input: PasswordInput) => api.put<void>("/api/v1/me/password", input);
