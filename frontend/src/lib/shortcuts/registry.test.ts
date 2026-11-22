import { describe, expect, it } from "vitest";
import { ACTIONS, ACTION_IDS, ariaKeys, bindingFromEvent, bindingsFor, findConflict, keyParts, matches, refusal, sameBinding } from "./registry";

const press = (code: string, key: string, mods: Partial<Record<"ctrlKey" | "altKey" | "shiftKey" | "metaKey", boolean>> = {}) => ({
  code,
  key,
  ctrlKey: false,
  altKey: false,
  shiftKey: false,
  metaKey: false,
  ...mods,
});

describe("matches (D52)", () => {
  it("keeps the search shortcuts as they were", () => {
    const [slash] = ACTIONS["search.open"].defaults;
    const [combo] = ACTIONS["search.openCombo"].defaults;
    expect(matches(press("Slash", "/"), slash)).toBe(true);
    // German layout: "/" is Shift+7.
    expect(matches(press("Digit7", "/", { shiftKey: true }), slash)).toBe(true);
    expect(matches(press("Slash", "/", { ctrlKey: true }), slash)).toBe(false);
    expect(matches(press("KeyK", "k", { ctrlKey: true }), combo)).toBe(true);
    expect(matches(press("KeyK", "k", { metaKey: true }), combo)).toBe(true);
    // Dvorak: the key that types "k" sits on KeyV.
    expect(matches(press("KeyV", "k", { ctrlKey: true }), combo)).toBe(true);
    expect(matches(press("KeyK", "k"), combo)).toBe(false);
    // Exact modifiers: Ctrl+Shift+K stays Firefox's console.
    expect(matches(press("KeyK", "K", { ctrlKey: true, shiftKey: true }), combo)).toBe(false);
  });

  it("matches macOS Option combos by physical key", () => {
    const [toggle] = ACTIONS["compass.toggle"].defaults;
    expect(matches(press("KeyC", "Ç", { altKey: true, shiftKey: true }), toggle)).toBe(true);
    expect(matches(press("KeyC", "C", { altKey: true, shiftKey: true }), toggle)).toBe(true);
    expect(matches(press("KeyC", "c", { altKey: true }), toggle)).toBe(false);
  });
});

describe("bindingsFor", () => {
  it("uses the override, nothing when disabled, and never overrides fixed menu keys", () => {
    expect(bindingsFor("search.open", { "search.open": { key: "s" } })).toEqual([{ key: "s" }]);
    expect(bindingsFor("search.open", { "search.open": null })).toEqual([]);
    expect(bindingsFor("compass.close", { "compass.close": null })).toEqual(ACTIONS["compass.close"].defaults);
    expect(bindingsFor("editor.save", undefined)).toEqual(ACTIONS["editor.save"].defaults);
  });
});

describe("conflicts and refusals", () => {
  it("finds the action that already uses a binding", () => {
    expect(findConflict("compass.toggle", { code: "KeyK", ctrl: true }, {}, false)).toBe("search.openCombo");
    expect(findConflict("compass.toggle", { code: "KeyK", meta: true }, {}, true)).toBe("search.openCombo");
    expect(findConflict("compass.toggle", { code: "KeyK", meta: true }, {}, false)).toBeNull();
    expect(findConflict("search.openCombo", { code: "KeyK", mod: true }, {}, false)).toBeNull();
    // A disabled shortcut frees its keys.
    expect(findConflict("compass.toggle", { key: "/" }, { "search.open": null }, false)).toBeNull();
    expect(findConflict("compass.toggle", { key: "/" }, {}, false)).toBe("search.open");
  });

  it("refuses browser and system combos and bare page keys", () => {
    expect(refusal({ code: "KeyT", mod: true })).toBe("reserved");
    expect(refusal({ code: "KeyI", ctrl: true, shift: true })).toBe("reserved");
    expect(refusal({ code: "ArrowLeft", alt: true })).toBe("reserved");
    expect(refusal({ code: "KeyF", alt: true })).toBe("reserved");
    expect(refusal({ code: "F5" })).toBe("reserved");
    expect(refusal({ code: "Enter" })).toBe("needsModifier");
    expect(refusal({ key: " " })).toBe("needsModifier");
    expect(refusal({ key: "g" })).toBeNull();
    for (const id of ACTION_IDS.filter((id) => !("fixed" in ACTIONS[id]))) {
      for (const b of ACTIONS[id].defaults) expect(refusal(b), id).toBeNull();
    }
  });

  it("gives every rebindable default its own keys", () => {
    for (const id of ACTION_IDS.filter((id) => ACTIONS[id].group !== "compass")) {
      for (const b of ACTIONS[id].defaults) expect(findConflict(id, b, {}, false), id).toBeNull();
    }
  });
});

describe("recording and display", () => {
  it("records combos by physical key and plain presses by character", () => {
    expect(bindingFromEvent(press("KeyJ", "j", { ctrlKey: true, shiftKey: true }), false)).toEqual({ code: "KeyJ", mod: true, shift: true });
    expect(bindingFromEvent(press("KeyJ", "∆", { metaKey: true, altKey: true }), true)).toEqual({ code: "KeyJ", mod: true, alt: true });
    expect(bindingFromEvent(press("KeyJ", "j", { ctrlKey: true }), true)).toEqual({ code: "KeyJ", ctrl: true });
    expect(bindingFromEvent(press("Digit7", "/", { shiftKey: true }), false)).toEqual({ key: "/" });
    expect(bindingFromEvent(press("ShiftLeft", "Shift", { shiftKey: true }), false)).toBeNull();
    expect(sameBinding({ code: "KeyJ", mod: true }, { code: "KeyJ", ctrl: true }, false)).toBe(true);
  });

  it("shows keys per platform and for assistive technology", () => {
    expect(keyParts({ code: "KeyC", alt: true, shift: true }, false)).toEqual(["Alt", "Shift", "C"]);
    expect(keyParts({ code: "KeyC", alt: true, shift: true }, true)).toEqual(["⌥", "⇧", "C"]);
    expect(keyParts({ code: "KeyK", mod: true }, true)).toEqual(["⌘", "K"]);
    expect(ariaKeys([{ key: "/" }, { code: "KeyK", mod: true }], false)).toBe("/ Control+K");
  });
});
