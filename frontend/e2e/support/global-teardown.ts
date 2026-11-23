import fs from "node:fs";
import { artisan } from "./artisan";
import { ACCOUNTS_FILE } from "./accounts";

/** Deletes every e2e-*@example.test account, the ones registered through the UI included, and the test course map. */
export default async function globalTeardown() {
  if (process.env.E2E_KEEP_ACCOUNTS) return;
  artisan("cybercompass:e2e-users", "--purge");
  artisan("cybercompass:e2e-course-map", "remove");
  fs.rmSync(ACCOUNTS_FILE, { force: true });
}
