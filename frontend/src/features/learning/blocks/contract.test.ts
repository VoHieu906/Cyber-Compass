import { readdirSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import { blockSchemas, sectionSourcesSchema } from "./schemas";

/*
 * Contract (ARCHITECTURE D18): for every block type, the zod mirror accepts and rejects exactly
 * the fixtures the backend's JSON Schemas and cross-reference checks do. The fixtures live with
 * the backend schemas, and Pest runs the same files through the BlockRegistry.
 */
const FIXTURES = resolve(__dirname, "../../../../../backend/app/Modules/Content/Blocks/fixtures");
const load = (name: string): unknown[] => JSON.parse(readFileSync(`${FIXTURES}/${name}`, "utf8"));
const types = Object.keys(blockSchemas) as (keyof typeof blockSchemas)[];

it("has a zod schema for every backend fixture set, and fixtures for every schema", () => {
  const fixtureTypes = new Set(readdirSync(FIXTURES).map((f) => f.replace(/\.(in)?valid\.json$/, "")));
  expect([...fixtureTypes].sort()).toEqual([...types].sort());
});

describe.each(types)("%s contract", (type) => {
  const schema = blockSchemas[type];

  it.each(load(`${type}.valid.json`).map((p, i) => [i, p]))("accepts valid fixture #%i", (_i, payload) => {
    expect(schema.safeParse(payload).error?.issues ?? []).toEqual([]);
  });

  it.each(load(`${type}.invalid.json`).map((p, i) => [i, p]))("rejects invalid fixture #%i", (_i, payload) => {
    expect(schema.safeParse(payload).success).toBe(false);
  });
});

// Section sources (D68) share one schema across block types; Pest runs the same fixtures.
describe("section sources contract", () => {
  const dir = resolve(__dirname, "../../../../../backend/app/Modules/Content/Documents/fixtures");
  const fixtures = (kind: string): unknown[] => JSON.parse(readFileSync(`${dir}/sources.${kind}.json`, "utf8"));

  it.each(fixtures("valid").map((s, i) => [i, s]))("accepts valid fixture #%i", (_i, sources) => {
    expect(sectionSourcesSchema.safeParse(sources).error?.issues ?? []).toEqual([]);
  });

  it.each(fixtures("invalid").map((s, i) => [i, s]))("rejects invalid fixture #%i", (_i, sources) => {
    expect(sectionSourcesSchema.safeParse(sources).success).toBe(false);
  });
});
