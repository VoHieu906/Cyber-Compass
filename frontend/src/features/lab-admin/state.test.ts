import { describe, expect, it } from "vitest";
import { canonical, entryStatus, kindFields, messagesAt, mergeMessages, newCheckpoint, sameContent, withoutCheckpoints } from "./state";

describe("lab editor state", () => {
  it("does not call a different key order a change", () => {
    expect(sameContent({ a: 1, b: { c: [1, { y: 1, x: 2 }] } }, { b: { c: [1, { x: 2, y: 1 }] }, a: 1 })).toBe(true);
    expect(sameContent({ a: 1 }, { a: 2 })).toBe(false);
    expect(canonical([{ b: 1, a: 2 }])).toBe('[{"a":2,"b":1}]');
  });

  it("picks the messages at a path and below it, not a path that only starts alike", () => {
    const all = { "scenario.checkpoints.0.path": ["x"], "scenario.checkpoints": ["y"], "scenario.checkpointsX": ["z"], title: ["t"] };
    expect(Object.keys(messagesAt(all, "scenario.checkpoints"))).toEqual(["scenario.checkpoints.0.path", "scenario.checkpoints"]);
    expect(Object.keys(messagesAt(all, "title", "summary"))).toEqual(["title"]);
  });

  it("merges messages without repeating one", () => {
    expect(mergeMessages({ a: ["x"] }, { a: ["x", "y"], b: ["z"] })).toEqual({ a: ["x", "y"], b: ["z"] });
  });

  it("starts a checkpoint with a free id and the kind's own fields", () => {
    expect(newCheckpoint("read", ["task-1"])).toEqual({ id: "task-2", kind: "read", title: "", path: "/" });
    expect(kindFields({ id: "a", title: "T", kind: "answer", hint: "h", answers: ["1"] })).toEqual({ answers: ["1"] });
  });

  it("separates the checkpoints from the rest of a scenario", () => {
    expect(withoutCheckpoints({ hostname: "h", checkpoints: [] })).toEqual({ hostname: "h" });
  });

  it("reads an entry as failing when the check named it", () => {
    expect(entryStatus({ "solutions.solutions.1": ["no"] }, "solutions", 1)).toBe("fail");
    expect(entryStatus({ "solutions.solutions.1": ["no"] }, "solutions", 0)).toBe("pass");
  });
});
