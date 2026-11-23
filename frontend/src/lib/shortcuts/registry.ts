/**
 * Keyboard shortcut registry (D52). Every shortcut in the app is an action here, so the settings
 * tab can list, rebind, reset and disable them. Pure: no React, no DOM beyond KeyboardEvent fields.
 *
 * Matching rule (D52): a binding with `code` is a physical key plus exact modifiers, so macOS
 * Option combos work (⌥⇧C reports "Ç" as its key); a letter combo without Alt also matches the
 * typed letter, as before. A binding with `key` is a typed character (such as "/", which needs
 * Shift on some layouts), so Shift is ignored and Ctrl, Alt and Meta must be up.
 */

export type Binding = {
  code?: string;
  key?: string;
  /** Ctrl on Windows and Linux, ⌘ on macOS: either one matches. */
  mod?: boolean;
  ctrl?: boolean;
  alt?: boolean;
  shift?: boolean;
  meta?: boolean;
};

export type ShortcutGroup = "general" | "compass" | "search" | "editor" | "courseMap";

export type ShortcutAction = {
  group: ShortcutGroup;
  defaults: Binding[];
  /** Fires while focus is in a field (combos that type nothing). */
  inFields?: boolean;
  /** Keys of the menu pattern (WAI-ARIA): listed, not rebindable. */
  fixed?: boolean;
};

/** Action ids are also stored as override keys (D53): letters, dots, camelCase. */
export const ACTIONS = {
  "compass.toggle": { group: "general", defaults: [{ code: "KeyC", alt: true, shift: true }], inFields: true },
  "search.open": { group: "search", defaults: [{ key: "/" }] },
  "search.openCombo": { group: "search", defaults: [{ code: "KeyK", mod: true }], inFields: true },
  "editor.save": { group: "editor", defaults: [{ code: "KeyS", mod: true }], inFields: true },
  "compass.next": { group: "compass", defaults: [{ code: "ArrowRight" }, { code: "Tab" }], fixed: true },
  "compass.previous": { group: "compass", defaults: [{ code: "ArrowLeft" }, { code: "Tab", shift: true }], fixed: true },
  "compass.enter": { group: "compass", defaults: [{ code: "Enter" }, { code: "ArrowDown" }, { code: "Space" }], fixed: true },
  "compass.back": { group: "compass", defaults: [{ code: "ArrowUp" }, { code: "Backspace" }], fixed: true },
  "compass.first": { group: "compass", defaults: [{ code: "Home" }], fixed: true },
  "compass.last": { group: "compass", defaults: [{ code: "End" }], fixed: true },
  "compass.close": { group: "compass", defaults: [{ code: "Escape" }], fixed: true },
  "courseMap.forward": { group: "courseMap", defaults: [{ code: "ArrowRight" }], fixed: true },
  "courseMap.backward": { group: "courseMap", defaults: [{ code: "ArrowLeft" }], fixed: true },
  "courseMap.next": { group: "courseMap", defaults: [{ code: "ArrowDown" }], fixed: true },
  "courseMap.previous": { group: "courseMap", defaults: [{ code: "ArrowUp" }], fixed: true },
  "courseMap.open": { group: "courseMap", defaults: [{ code: "Enter" }, { code: "Space" }], fixed: true },
  "courseMap.close": { group: "courseMap", defaults: [{ code: "Escape" }], fixed: true },
} as const satisfies Record<string, ShortcutAction>;

export type ActionId = keyof typeof ACTIONS;
export const ACTION_IDS = Object.keys(ACTIONS) as ActionId[];

/** Stored overrides: a binding, or null when the learner disabled the shortcut. */
export type Overrides = Partial<Record<string, Binding | null>>;

/** The bindings in force for an action: its override (none when disabled) or its defaults. */
export function bindingsFor(id: ActionId, overrides: Overrides | undefined): Binding[] {
  const action: ShortcutAction = ACTIONS[id];
  if (action.fixed || !overrides || !(id in overrides)) return action.defaults;
  const override = overrides[id];
  return override ? [override] : [];
}

type KeyLike = Pick<KeyboardEvent, "code" | "key" | "ctrlKey" | "altKey" | "shiftKey" | "metaKey">;

/** True when a key press is one of an action's bindings (for widget keys handled in place, like the course map's). */
export const pressed = (e: KeyLike, id: ActionId, overrides?: Overrides) => bindingsFor(id, overrides).some((b) => matches(e, b));

export function matches(e: KeyLike, b: Binding): boolean {
  if (b.key !== undefined) return e.key === b.key && !e.ctrlKey && !e.altKey && !e.metaKey;
  if (!b.code) return false;
  const ctrlMeta = b.mod ? e.ctrlKey || e.metaKey : e.ctrlKey === Boolean(b.ctrl) && e.metaKey === Boolean(b.meta);
  if (!ctrlMeta || e.altKey !== Boolean(b.alt) || e.shiftKey !== Boolean(b.shift)) return false;
  if (e.code === b.code) return true;
  // Another layout puts the letter elsewhere: Ctrl/⌘+K follows the typed "k", as it did before D52.
  return !b.alt && /^Key[A-Z]$/.test(b.code) && e.key.toLowerCase() === b.code.slice(3).toLowerCase();
}

/** True when the key press belongs to a field the person is typing in. */
export const typing = (target: EventTarget | null) =>
  target instanceof HTMLElement && (target.isContentEditable || ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName));

const MODIFIER_CODES = /^(Control|Shift|Alt|Meta|OS)(Left|Right)?$|^(CapsLock|Fn|AltGraph)$/;

/**
 * The binding a key press would record in the settings tab, or null while only modifiers are
 * down. A press with Ctrl, Alt or Meta records the physical key; otherwise the typed character.
 */
export function bindingFromEvent(e: KeyLike, isMac: boolean): Binding | null {
  if (MODIFIER_CODES.test(e.code) || !e.code) return null;
  if (!e.ctrlKey && !e.altKey && !e.metaKey) {
    if (e.key.length === 1 && e.key !== " ") return { key: e.key };
    return { code: e.code, ...(e.shiftKey && { shift: true }) };
  }
  const b: Binding = { code: e.code };
  // The platform's command key is stored as `mod`, so the binding travels between a Mac and a PC.
  if (isMac ? e.metaKey && !e.ctrlKey : e.ctrlKey && !e.metaKey) b.mod = true;
  else {
    if (e.ctrlKey) b.ctrl = true;
    if (e.metaKey) b.meta = true;
  }
  if (e.altKey) b.alt = true;
  if (e.shiftKey) b.shift = true;
  return b;
}

/** Ctrl and ⌘ as a set: `mod` stands for whichever the platform uses. */
const commandKeys = (b: Binding, isMac: boolean) =>
  b.mod ? (isMac ? "meta" : "ctrl") : `${b.ctrl ? "ctrl" : ""}${b.meta ? "meta" : ""}`;

export function sameBinding(a: Binding, b: Binding, isMac: boolean): boolean {
  if (a.key !== undefined || b.key !== undefined) return a.key === b.key;
  return a.code === b.code && Boolean(a.alt) === Boolean(b.alt) && Boolean(a.shift) === Boolean(b.shift) && commandKeys(a, isMac) === commandKeys(b, isMac);
}

/** The rebindable action, other than `id`, that already uses `binding`; fixed keys only bind inside their widget (the compass, the course map). */
export function findConflict(id: ActionId, binding: Binding, overrides: Overrides | undefined, isMac: boolean): ActionId | null {
  for (const other of ACTION_IDS) {
    if (other === id || "fixed" in ACTIONS[other]) continue;
    if (bindingsFor(other, overrides).some((b) => sameBinding(b, binding, isMac))) return other;
  }
  return null;
}

const RESERVED_LETTERS_WITH_COMMAND = new Set(["KeyT", "KeyW", "KeyN", "KeyQ", "KeyR", "KeyL", "KeyP", "KeyF", "KeyH", "KeyJ", "KeyD", "KeyO", "KeyA", "KeyC", "KeyV", "KeyX", "KeyZ", "KeyY", "Tab", "Equal", "Minus", "Digit0", "Digit1", "Digit2", "Digit3", "Digit4", "Digit5", "Digit6", "Digit7", "Digit8", "Digit9", "PageUp", "PageDown"]);
const RESERVED_WITH_COMMAND_SHIFT = new Set(["KeyT", "KeyN", "KeyW", "KeyI", "KeyJ", "KeyC", "KeyK", "KeyP", "KeyB", "Delete", "Tab", "KeyZ"]);

/**
 * Why a binding cannot be used for a global shortcut, or null when it can. Refuses combos the
 * browser or the system keeps (new tab, close, reload, address bar, find, zoom, copy and paste,
 * developer tools, back and forward, switching windows) and bare keys that operate the page.
 */
export function refusal(b: Binding): "reserved" | "needsModifier" | null {
  if (b.key !== undefined) return b.key.trim() === "" ? "needsModifier" : null;
  const code = b.code ?? "";
  const command = b.mod || b.ctrl || b.meta;
  if (/^F([1-9]|1[0-2])$/.test(code) && !command && !b.alt) return "reserved";
  if (!command && !b.alt) return "needsModifier";
  if (command && !b.alt && (b.shift ? RESERVED_WITH_COMMAND_SHIFT : RESERVED_LETTERS_WITH_COMMAND).has(code)) return "reserved";
  if (b.alt && !command && ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown", "Home", "F4", "Tab", "Space", "Escape", "KeyD"].includes(code)) return "reserved";
  if (b.alt && !command && !b.shift && /^Key[A-Z]$/.test(code)) return "reserved"; // browser menus (Alt+F, Alt+E…) and access keys
  return null;
}

const CODE_LABELS: Record<string, string> = {
  ArrowLeft: "←", ArrowRight: "→", ArrowUp: "↑", ArrowDown: "↓", Space: "Space", Enter: "Enter", Escape: "Esc",
  Backspace: "Backspace", Tab: "Tab", Slash: "/", Backslash: "\\", Period: ".", Comma: ",", Semicolon: ";",
  Quote: "'", BracketLeft: "[", BracketRight: "]", Backquote: "`", Minus: "-", Equal: "=", Home: "Home", End: "End",
};

const keyLabel = (code: string) => CODE_LABELS[code] ?? code.replace(/^(Key|Digit|Numpad)/, "");

/** Keys to show, one string per key: ["Ctrl", "K"] or ["⌘", "K"]. */
export function keyParts(b: Binding, isMac: boolean): string[] {
  if (b.key !== undefined) return [b.key.toUpperCase()];
  const parts: string[] = [];
  if (b.ctrl || (b.mod && !isMac)) parts.push(isMac ? "⌃" : "Ctrl");
  if (b.alt) parts.push(isMac ? "⌥" : "Alt");
  if (b.shift) parts.push(isMac ? "⇧" : "Shift");
  if (b.meta || (b.mod && isMac)) parts.push(isMac ? "⌘" : "Meta");
  parts.push(keyLabel(b.code ?? ""));
  return parts;
}

/** The `aria-keyshortcuts` value (WAI-ARIA names), e.g. "/ Control+K". */
export function ariaKeys(bindings: Binding[], isMac: boolean): string {
  return bindings
    .map((b) => {
      if (b.key !== undefined) return b.key === " " ? "Space" : b.key;
      const mods = [b.ctrl || (b.mod && !isMac) ? "Control" : "", b.alt ? "Alt" : "", b.shift ? "Shift" : "", b.meta || (b.mod && isMac) ? "Meta" : ""];
      return [...mods.filter(Boolean), keyLabel(b.code ?? "")].join("+");
    })
    .join(" ");
}

export const isMacPlatform = () => typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
