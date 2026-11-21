import fs from "node:fs";
import path from "node:path";

export const ACCOUNTS_FILE = path.resolve(__dirname, "../.auth/accounts.json");

/** Written by global-setup: one password for every throwaway account of the run. */
export type Accounts = { password: string; learners: string[]; admins: string[] };

/** Fixed roles, so specs running side by side never share an account (or its rate limits). */
export const LEARNER = { auth: 0, labs: 1, search: 2, phone: 3, security: 4 } as const;

export type Account = { email: string; password: string };

export function account(role: keyof typeof LEARNER | "admin"): Account {
  const accounts = JSON.parse(fs.readFileSync(ACCOUNTS_FILE, "utf8")) as Accounts;
  const email = role === "admin" ? accounts.admins[0] : accounts.learners[LEARNER[role]];
  return { email, password: accounts.password };
}
