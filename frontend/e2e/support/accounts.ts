import fs from "node:fs";
import path from "node:path";

export const ACCOUNTS_FILE = path.resolve(__dirname, "../.auth/accounts.json");
/** Saved sessions per account (support/test.ts `signIn`), cleared by global-setup. */
export const SESSIONS_DIR = path.resolve(__dirname, "../.auth/sessions");

/** Written by global-setup: one password for every throwaway account of the run. */
export type Accounts = { password: string; learners: string[]; admins: string[] };

/** Fixed roles, so specs running side by side never share an account (or its rate limits). */
export const LEARNER = {
  auth: 0,
  labs: 1,
  search: 2,
  phone: 3,
  security: 4,
  perf: 5,
  sweep: 6,
  sweep2: 7,
  uploads: 8,
  compass: 9,
  compass2: 10,
  courseMap: 11,
} as const;

export type Account = { email: string; password: string };

/**
 * "admin" is shared by the admin, blog, security, performance and sweep specs; "mapAdmin" is a
 * second admin for the course map suggestions spec and "packagesAdmin" a third for the content
 * packages spec, because the admin rate limit (120 a minute)
 * is per user and the specs run in parallel. Never raise the limit for tests.
 */
export function account(role: keyof typeof LEARNER | "admin" | "mapAdmin" | "packagesAdmin"): Account {
  const accounts = JSON.parse(fs.readFileSync(ACCOUNTS_FILE, "utf8")) as Accounts;
  const email =
    role === "admin"
      ? accounts.admins[0]
      : role === "mapAdmin"
        ? accounts.admins[1]
        : role === "packagesAdmin"
          ? accounts.admins[2]
          : accounts.learners[LEARNER[role]];
  return { email, password: accounts.password };
}
