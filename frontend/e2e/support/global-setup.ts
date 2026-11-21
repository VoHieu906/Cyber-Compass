import fs from "node:fs";
import path from "node:path";
import { artisan, artisanJson } from "./artisan";
import { ACCOUNTS_FILE, type Accounts } from "./accounts";

/** Purges what an interrupted run left, then makes this run's throwaway accounts. */
export default async function globalSetup() {
  artisan("cybercompass:e2e-users", "--purge");
  const accounts = artisanJson<Accounts>("cybercompass:e2e-users", "--learners=8", "--admins=1");
  fs.mkdirSync(path.dirname(ACCOUNTS_FILE), { recursive: true });
  fs.writeFileSync(ACCOUNTS_FILE, JSON.stringify(accounts));
}
