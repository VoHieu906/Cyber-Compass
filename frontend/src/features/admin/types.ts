/** Admin console API shapes (backend app/Modules/Admin/Http/Resources). */

export type Role = "learner" | "admin";
export type AccountStatus = "active" | "suspended";

export type AdminUser = {
  id: number;
  name: string;
  email: string;
  role: Role;
  status: AccountStatus;
  avatar_url: string | null;
  email_verified: boolean;
  created_at: string | null;
  last_login_at: string | null;
  is_self: boolean;
};

export type AuditEntry = {
  id: number;
  action: string;
  actor: { id: number; name: string } | null;
  subject: { type: string; id: number | null; label: string | null };
  changes: Record<string, unknown>;
  created_at: string | null;
};

export type AdminUserDetail = AdminUser & {
  locale: string;
  education_level: string | null;
  suspension: { at: string; reason: string | null } | null;
  is_last_active_admin: boolean;
  stats: { enrollments: number; completed_lessons: number };
  recent_activity: AuditEntry[];
};

export type AdminOverview = {
  users: { total: number; admins: number; suspended: number; new_last_7_days: number; active_last_7_days: number };
  lessons: Record<"draft" | "published" | "archived", number>;
  recent_activity: AuditEntry[];
};

export const USER_SORTS = ["-created_at", "created_at", "name", "-name", "-last_login_at", "last_login_at"] as const;
export type UserSort = (typeof USER_SORTS)[number];

export type UserQuery = {
  q: string;
  role: Role | "";
  status: AccountStatus | "";
  sort: UserSort;
  page: number;
};

export const SUBJECT_TYPES = ["user", "lesson", "chapter", "course", "media"] as const;
export type SubjectType = (typeof SUBJECT_TYPES)[number];

export type AuditQuery = {
  actor: number | null;
  subjectType: SubjectType | "";
  subjectId: number | null;
  page: number;
};
