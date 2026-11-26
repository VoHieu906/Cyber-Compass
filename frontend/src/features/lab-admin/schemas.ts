import { z } from "zod";
import "@/lib/zod-config";
import type { Engine } from "@/features/labs/types";
import type { PathMessages } from "./types";

/*
 * zod mirrors of the engines' scenario JSON Schemas (backend/app/Modules/Labs/schemas/<engine>.json,
 * D76). schemas.test.ts holds both sides to the same fixtures. They give the editor feedback as you
 * type; what a schema cannot say (a checkpoint's file or route exists, an address is valid) is the
 * server's reference check, and the server stays the authority on save.
 */

const id = z
  .string()
  .regex(/^[a-z0-9]+(-[a-z0-9]+)*$/, "Use lowercase words joined by hyphens.")
  .max(60);
const title = z.string().min(1).max(300);
const hint = z.string().max(500).optional();
const path = z.string().regex(/^\//, "Start with /.");
const ident = z
  .string()
  .regex(
    /^[a-z][a-z0-9-]{0,31}$/,
    "Use lowercase letters, digits and hyphens, starting with a letter (at most 32 characters).",
  );

const answer = z.strictObject({
  id,
  title,
  hint,
  kind: z.literal("answer"),
  answers: z.array(z.string().min(1).max(200)).min(1).max(20),
});

const checkpoints = <T extends z.core.$ZodTypeDiscriminable>(...kinds: T[]) =>
  z
    .array(z.discriminatedUnion("kind", [answer, ...kinds] as [typeof answer, ...T[]]))
    .min(1)
    .max(20);

const shell = z.strictObject({
  hostname: ident,
  user: ident,
  home: path,
  cwd: path,
  files: z
    .record(path, z.string())
    .refine((f) => Object.keys(f).length >= 1 && Object.keys(f).length <= 200, "Use 1 to 200 files."),
  dirs: z.array(path).max(100).optional(),
  checkpoints: checkpoints(z.strictObject({ id, title, hint, kind: z.literal("read"), path })),
});

const rule = z.strictObject({
  action: z.enum(["allow", "deny"]),
  proto: z.enum(["tcp", "udp", "any"]),
  port: z.number().int().min(1).max(65535).nullable().optional(),
  source: z.string().max(18),
});

const firewall = z.strictObject({
  server: z.strictObject({ name: z.string().min(1).max(60), ip: z.string().max(15) }),
  default: z.enum(["allow", "deny"]),
  rules: z.array(rule).max(20),
  checkpoints: checkpoints(
    z.strictObject({
      id,
      title,
      hint,
      kind: z.literal("traffic"),
      packets: z
        .array(
          z.strictObject({
            proto: z.enum(["tcp", "udp"]),
            port: z.number().int().min(1).max(65535),
            source: z.string().max(15),
            expect: z.enum(["allow", "deny"]),
          }),
        )
        .min(1)
        .max(20),
    }),
  ),
});

const method = z.enum(["GET", "POST", "PUT", "DELETE"]);
// An empty object reaches the server as an empty list once PHP has handled it.
const strings = z.union([
  z.record(z.string(), z.string().max(1000)).refine((r) => Object.keys(r).length <= 20, "Use at most 20."),
  z.array(z.never()).max(0),
]);
const response = z.strictObject({
  status: z.number().int().min(100).max(599),
  headers: strings,
  body: z.string().max(20000),
});
const urlPath = z.string().regex(/^\//, "Start with /.").max(200);

const http = z.strictObject({
  app: z.strictObject({ name: z.string().min(1).max(60), origin: z.string().max(200) }),
  session: z.strictObject({ name: z.string().min(1).max(64), value: z.string().min(1).max(200) }),
  captured: z
    .array(
      z.strictObject({
        request: z.strictObject({
          method,
          path: urlPath,
          headers: strings.optional(),
          body: z.string().max(2000).optional(),
        }),
        response,
      }),
    )
    .max(20)
    .optional(),
  routes: z
    .array(
      z.strictObject({
        id,
        method,
        path: urlPath,
        cookie: z.strictObject({ name: z.string(), value: z.string() }).optional(),
        response,
        unauthorized: response.optional(),
      }),
    )
    .min(1)
    .max(40),
  not_found: response.optional(),
  checkpoints: checkpoints(z.strictObject({ id, title, hint, kind: z.literal("request"), route: id })),
});

export const scenarioSchemas: Record<Engine, z.ZodType> = {
  "linux-shell": shell,
  "firewall-rules": firewall,
  "http-inspector": http,
};

/** The schema problems of a scenario, keyed by path in the definition (`scenario.checkpoints.0.kind`). */
export function checkScenario(engine: Engine, scenario: unknown): PathMessages {
  const result = scenarioSchemas[engine].safeParse(scenario);
  if (result.success) return {};
  const out: PathMessages = {};
  for (const issue of result.error.issues) {
    const where = ["scenario", ...issue.path.map(String)].join(".");
    (out[where] ??= []).push(issue.message);
  }
  return out;
}
