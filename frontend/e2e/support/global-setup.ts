import fs from "node:fs";
import path from "node:path";
import { artisan, artisanJson } from "./artisan";
import { ACCOUNTS_FILE, SESSIONS_DIR, type Accounts } from "./accounts";

/** Purges what an interrupted run left, then makes this run's throwaway accounts and the test course map (F3). */
export default async function globalSetup() {
  artisan("cybercompass:e2e-users", "--purge");
  artisan("cybercompass:e2e-course-map", "install");
  const accounts = artisanJson<Accounts>("cybercompass:e2e-users", "--learners=12", "--admins=1");
  fs.mkdirSync(path.dirname(ACCOUNTS_FILE), { recursive: true });
  fs.writeFileSync(ACCOUNTS_FILE, JSON.stringify(accounts));
  fs.rmSync(SESSIONS_DIR, { recursive: true, force: true });
  fs.mkdirSync(SESSIONS_DIR, { recursive: true });
}
