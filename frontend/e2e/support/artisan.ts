import { execFileSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";

/**
 * Runs an artisan command in the API container: `docker compose exec -T api php artisan …` from the
 * repository root. E2E_ARTISAN replaces the prefix when the API runs elsewhere (space separated,
 * for example "php ../backend/artisan").
 */
export function artisan(...args: string[]): string {
  const prefix = process.env.E2E_ARTISAN?.split(" ").filter(Boolean) ?? [
    "docker",
    "compose",
    "exec",
    "-T",
    "api",
    "php",
    "artisan",
  ];
  const [command, ...rest] = prefix;
  return execFileSync(executable(command), [...rest, ...args], {
    cwd: path.resolve(__dirname, "../../.."),
    env: { ...process.env, PATH: dockerPath() },
    encoding: "utf8",
    stdio: ["ignore", "pipe", "pipe"],
  });
}

/** The last JSON line of a command's output. */
export function artisanJson<T>(...args: string[]): T {
  const line = artisan(...args)
    .trim()
    .split(/\r?\n/)
    .reverse()
    .find((l) => l.startsWith("{"));
  if (!line) throw new Error(`artisan ${args.join(" ")} printed no JSON`);
  return JSON.parse(line) as T;
}

// Without a shell, Windows needs the .exe name (and Docker Desktop may not be on PATH).
function executable(command: string): string {
  return process.platform === "win32" && !command.endsWith(".exe") ? `${command}.exe` : command;
}

function dockerPath(): string {
  const current = process.env.PATH ?? "";
  if (process.platform !== "win32" || !process.env.LOCALAPPDATA) return current;
  const desktop = path.join(process.env.LOCALAPPDATA, "Programs", "DockerDesktop", "resources", "bin");
  return fs.existsSync(desktop) ? `${desktop}${path.delimiter}${current}` : current;
}
