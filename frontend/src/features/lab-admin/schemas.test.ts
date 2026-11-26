import { readdirSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import type { Engine } from "@/features/labs/types";
import { checkScenario, scenarioSchemas } from "./schemas";

/*
 * Contract (D76): for every engine, the zod mirror accepts the starter scenario and rejects every
 * invalid fixture the backend's JSON Schema rejects. Fixtures marked "reference" are caught by the
 * server's reference checks only (a file or route that does not exist, an address that is not one),
 * so the mirror lets them through on purpose. Pest runs the same files through LabDefinitionValidator.
 */
const DIR = resolve(__dirname, "../../../../backend/app/Modules/Labs/schemas");
const engines = Object.keys(scenarioSchemas) as Engine[];

type Invalid = { name: string; check: "schema" | "reference"; set: Record<string, unknown>; error: string };
const read = <T>(file: string): T => JSON.parse(readFileSync(`${DIR}/${file}`, "utf8"));

/** Applies a "dotted.path" change to a copy of the definition ("scenario.files./x" keeps its file name whole). */
function apply(def: Record<string, unknown>, set: Record<string, unknown>) {
  const copy = structuredClone(def);
  for (const [path, value] of Object.entries(set)) {
    const keys = path.includes("/") ? ["scenario", "files", path.slice("scenario.files.".length)] : path.split(".");
    let ref = copy as Record<string, unknown>;
    for (const key of keys.slice(0, -1)) ref = ref[key] as Record<string, unknown>;
    ref[keys.at(-1)!] = value;
  }
  return copy;
}

it("has a schema for every backend engine schema file", () => {
  const files = readdirSync(DIR)
    .filter((f) => f.endsWith(".json"))
    .map((f) => f.replace(".json", ""));
  expect(files.sort()).toEqual([...engines].sort());
});

describe.each(engines)("%s contract", (engine) => {
  const starter = read<{ scenario: Record<string, unknown> }>(`fixtures/${engine}/starter.json`);

  it("accepts the starter scenario", () => {
    expect(checkScenario(engine, starter.scenario)).toEqual({});
  });

  const cases = read<Invalid[]>(`fixtures/${engine}/invalid.json`);
  it.each(cases.filter((c) => c.check === "schema").map((c) => [c.name, c] as const))("rejects: %s", (_name, c) => {
    const def = apply({ scenario: starter.scenario }, c.set);
    const found = Object.keys(checkScenario(engine, def.scenario));
    expect(found.some((p) => p.startsWith(c.error))).toBe(true);
  });

  it.each(cases.filter((c) => c.check === "reference").map((c) => [c.name, c] as const))(
    "leaves to the server: %s",
    (_name, c) => {
      const def = apply({ scenario: starter.scenario }, c.set);
      expect(checkScenario(engine, def.scenario)).toEqual({});
    },
  );
});

it("reads an empty header object that came back from the server as an empty list", () => {
  const starter = read<{ scenario: Record<string, unknown> }>("fixtures/http-inspector/starter.json");
  const scenario = structuredClone(starter.scenario) as { routes: { response: { headers: unknown } }[] };
  scenario.routes[0].response.headers = [];
  expect(checkScenario("http-inspector", scenario)).toEqual({});
});
