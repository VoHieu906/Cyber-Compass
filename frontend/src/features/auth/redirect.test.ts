import { describe, expect, it } from "vitest";
import { HOME_AFTER_LOGIN, safeNext } from "./redirect";

describe("safeNext", () => {
  it("keeps same-site paths", () => {
    expect(safeNext("/settings/security")).toBe("/settings/security");
    expect(safeNext("/courses?page=2")).toBe("/courses?page=2");
  });

  it.each([
    ["absolute URL", "https://evil.example/phish"],
    ["protocol-relative URL", "//evil.example"],
    ["backslash trick", "/\\evil.example"],
    ["javascript URL", "javascript:alert(1)"],
    ["relative path", "settings"],
    ["empty", ""],
    ["missing", null],
  ])("falls back for %s", (_label, value) => {
    expect(safeNext(value)).toBe(HOME_AFTER_LOGIN);
  });
});
