import { readdirSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import { architectureSchema, courseGraphSchema, SHAPES } from "./schemas";

/* The zod mirror agrees with the backend contract (MapSchemas.php) on every shared fixture. */

const ROOT = resolve(__dirname, "../../../../../backend/app/Modules/Content/CourseMap");
const read = (name: string) => JSON.parse(readFileSync(resolve(ROOT, "fixtures", name), "utf8"));

describe("course map contract", () => {
  it("has the same shapes as the backend, each with a schema and both fixtures", () => {
    const schemas = readdirSync(resolve(ROOT, "schemas/shapes"))
      .map((f) => f.replace(/\.json$/, ""))
      .sort();
    expect(schemas).toEqual([...SHAPES].sort());
    for (const shape of SHAPES) {
      expect(() => read(`${shape}.valid.json`)).not.toThrow();
      expect(() => read(`${shape}.invalid.json`)).not.toThrow();
    }
  });

  it.each(SHAPES)("accepts the valid %s fixture and rejects the invalid one", (shape) => {
    expect(architectureSchema.safeParse(read(`${shape}.valid.json`)).error?.issues).toBeUndefined();
    expect(architectureSchema.safeParse(read(`${shape}.invalid.json`)).success).toBe(false);
  });

  it("accepts the valid course graph fixture and rejects the invalid one at the same fields", () => {
    expect(courseGraphSchema.safeParse(read("course-graph.valid.json")).success).toBe(true);
    const paths = courseGraphSchema
      .safeParse(read("course-graph.invalid.json"))
      .error!.issues.map((i) => i.path.join("."));
    expect(paths).toEqual(expect.arrayContaining(["problem", "layout", "placement.lesson-a.col", "edges.0.type"]));
  });

  it("applies the reference rules the backend applies", () => {
    const base = { version: 1, problem: "P", shape: "tree", edges: [] } as const;
    expect(
      architectureSchema.safeParse({
        ...base,
        nodes: [
          { id: "a", label: "A" },
          { id: "b", label: "B" },
        ],
      }).success,
    ).toBe(false);
    expect(
      architectureSchema.safeParse({
        ...base,
        nodes: [
          { id: "a", label: "A" },
          { id: "b", label: "B", parent: "a" },
        ],
      }).success,
    ).toBe(true);
    expect(
      architectureSchema.safeParse({
        ...base,
        shape: "pipeline",
        rows: ["x"],
        nodes: [
          { id: "a", label: "A" },
          { id: "b", label: "B" },
        ],
      }).success,
    ).toBe(false);
    expect(architectureSchema.safeParse({ ...base, shape: null, nodes: [{ id: "a", label: "A" }] }).success).toBe(
      false,
    );
    expect(
      architectureSchema.safeParse({
        ...base,
        shape: "pipeline",
        nodes: [
          { id: "a", label: "A" },
          { id: "b", label: "B" },
        ],
        edges: [{ from: "a", to: "zz" }],
      }).success,
    ).toBe(false);
  });
});
