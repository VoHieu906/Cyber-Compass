import { readdirSync, readFileSync, statSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

const SRC = path.resolve(__dirname, "..");

function sources(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const full = path.join(dir, name);
    if (statSync(full).isDirectory()) return sources(full);
    return /\.(ts|tsx)$/.test(name) && !/\.test\.tsx?$/.test(name) ? [full] : [];
  });
}

describe("zod configuration", () => {
  it("is loaded by every module that uses zod at runtime (the CSP forbids its eval probe)", () => {
    const users = sources(SRC).filter((file) => /^import \{[^}]*\} from "zod";/m.test(readFileSync(file, "utf8")));
    expect(users.length).toBeGreaterThan(0);
    for (const file of users) {
      if (file.endsWith(path.join("lib", "zod-config.ts"))) continue;
      expect(readFileSync(file, "utf8"), path.relative(SRC, file)).toContain('import "@/lib/zod-config";');
    }
  });
});
