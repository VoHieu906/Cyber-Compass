import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import type { Scene3d } from "./schema";
import { ancestors, isAnimated, stepState } from "./steps";

/* The zod-vs-JSON-Schema contract for these types is in features/learning/blocks/contract.test.ts. */
const FIXTURES = resolve(__dirname, "../../../../backend/app/Modules/Content/Blocks/fixtures");
const load = (name: string): unknown[] => JSON.parse(readFileSync(`${FIXTURES}/${name}`, "utf8"));

const scene = load("scene-3d.valid.json")[1] as Scene3d;

describe("guided steps", () => {
  it("shows everything and highlights nothing in the free view", () => {
    const state = stepState(scene, -1);
    expect([...state.visible].sort()).toEqual(["l1", "l2", "stack", "tag"]);
    expect(state.highlighted.size).toBe(0);
  });

  it("carries hides forward and takes the latest camera", () => {
    expect(stepState(scene, 0).camera).toBeNull();
    const second = stepState(scene, 1);
    expect(second.visible.has("tag")).toBe(false);
    expect([...second.highlighted]).toEqual(["l2"]);
    expect(second.camera).toEqual({ position: [0, 2, 4], target: [0, 0, 0] });
    expect(stepState(scene, 99).highlighted).toEqual(second.highlighted); // clamped to the last step
  });

  it("knows ancestors and whether anything moves", () => {
    expect(ancestors(scene, "l1")).toEqual(["stack"]);
    expect(isAnimated(scene)).toBe(true);
    expect(isAnimated({ ...scene, camera: { position: [0, 0, 5] }, links: [] })).toBe(false);
  });
});
