import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import { architectureSchema } from "@/lib/graph/shapes/schemas";
import { FILLABLE, fillFromBlock } from "./architecture-fill";

/* Every fillable block's valid fixture becomes a valid architecture linked back to the block. */

const FIXTURES = resolve(__dirname, "../../../../backend/app/Modules/Content/Blocks/fixtures");
const ID = "6f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30";

describe("fillFromBlock", () => {
  it.each(Object.keys(FILLABLE))("turns a %s block into a valid architecture of its shape", (type) => {
    const payloads: Record<string, unknown>[] = JSON.parse(
      readFileSync(resolve(FIXTURES, `${type}.valid.json`), "utf8"),
    );
    for (const payload of payloads) {
      const arch = fillFromBlock({ id: ID, type, heading: "Block", payload }, "The problem.")!;
      expect(arch.shape).toBe(FILLABLE[type]);
      expect(arch.nodes.length).toBeGreaterThan(0);
      expect(arch.nodes.every((n) => n.section === ID)).toBe(true);
      expect(architectureSchema.safeParse(arch).error?.issues).toBeUndefined();
    }
  });

  it("offers nothing for a block that is not saved yet or has no shape", () => {
    expect(fillFromBlock({ type: "flow", heading: null, payload: {} }, "P")).toBeNull();
    expect(fillFromBlock({ id: ID, type: "prose", heading: null, payload: { text: "x" } }, "P")).toBeNull();
  });
});
