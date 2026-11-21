import { describe, expect, it } from "vitest";
import { columnPath, labelEvery, MINUTE_STEPS, niceStep, niceTicks, stepTicks, yScale } from "./scale";

describe("chart scales", () => {
  it("picks 1, 2 or 5 steps and ends at the first clean value above the data", () => {
    expect(niceStep(100, 4)).toBe(50);
    expect(niceTicks(7, 4)).toEqual([0, 2, 4, 6, 8]);
    expect(niceTicks(23, 4)).toEqual([0, 10, 20, 30]);
    expect(niceTicks(0, 4, 5)).toEqual([0, 2, 4, 6]);
    expect(niceTicks(0)).toEqual([0, 1]);
  });

  it("steps durations in minutes and whole hours, never 100 or 200 minutes", () => {
    expect(stepTicks(25, MINUTE_STEPS)).toEqual([0, 10, 20, 30]);
    expect(stepTicks(240, MINUTE_STEPS)).toEqual([0, 60, 120, 180, 240]);
    expect(stepTicks(250, MINUTE_STEPS)).toEqual([0, 90, 180, 270]);
    expect(stepTicks(0, MINUTE_STEPS, 4, 30)).toEqual([0, 10, 20, 30]);
  });

  it("maps values onto the plot and clamps above the domain", () => {
    const y = yScale(100, 10, 110);
    expect([y(0), y(50), y(100), y(250)]).toEqual([110, 60, 10, 10]);
  });

  it("thins x labels so they stay apart, always keeping the latest", () => {
    const show = labelEvery(12, 20);
    expect([...Array(12).keys()].filter(show)).toEqual([2, 5, 8, 11]);
    expect([...Array(4).keys()].filter(labelEvery(4, 100))).toEqual([0, 1, 2, 3]);
  });

  it("rounds only the data end of a column and draws nothing for zero", () => {
    expect(columnPath(0, 20, 50, 50)).toBe("");
    expect(columnPath(0, 20, 10, 100)).toBe("M0,100 V14 Q0,10 4,10 H16 Q20,10 20,14 V100 Z");
    // A column shorter than the radius uses its height, so it never bulges below the baseline.
    expect(columnPath(0, 20, 98, 100)).toContain("V100");
    expect(columnPath(0, 20, 98, 100)).toContain("Q0,98 2,98");
  });
});
