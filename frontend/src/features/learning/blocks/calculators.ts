import { CHMOD_PATTERN, isCidr } from "./schemas";

/*
 * The calculator block's tools. Pure functions, run only in the learner's browser: nothing typed
 * into a calculator is stored or sent anywhere.
 */

// ---------- CIDR ----------

export type CidrResult = {
  prefix: number;
  address: string;
  mask: string;
  wildcard: string;
  network: string;
  broadcast: string;
  firstHost: string;
  lastHost: string;
  totalAddresses: number;
  usableHosts: number;
  addressBits: string;
  maskBits: string;
};

const toInt = (dotted: string) => dotted.split(".").reduce((acc, o) => ((acc << 8) | Number(o)) >>> 0, 0);
const toDotted = (n: number) => [24, 16, 8, 0].map((s) => (n >>> s) & 255).join(".");
const toBits = (n: number) =>
  (n >>> 0)
    .toString(2)
    .padStart(32, "0")
    .replace(/(.{8})(?!$)/g, "$1.");

export function cidr(input: string): CidrResult | null {
  const value = input.trim();
  if (!isCidr(value)) return null;
  const [addr, p] = value.split("/");
  const prefix = Number(p);
  const ip = toInt(addr);
  const mask = prefix === 0 ? 0 : (0xffffffff << (32 - prefix)) >>> 0;
  const network = (ip & mask) >>> 0;
  const broadcast = (network | ~mask) >>> 0;
  const total = 2 ** (32 - prefix);
  // /31 links use both addresses (RFC 3021); a /32 is a single host.
  const usable = prefix >= 31 ? total : total - 2;
  const first = prefix >= 31 ? network : network + 1;
  const last = prefix >= 31 ? broadcast : broadcast - 1;
  return {
    prefix,
    address: toDotted(ip),
    mask: toDotted(mask),
    wildcard: toDotted(~mask >>> 0),
    network: toDotted(network),
    broadcast: toDotted(broadcast),
    firstHost: toDotted(first),
    lastHost: toDotted(last),
    totalAddresses: total,
    usableHosts: usable,
    addressBits: toBits(ip),
    maskBits: toBits(mask),
  };
}

// ---------- chmod ----------

export type Who = "owner" | "group" | "others";
export type Perm = { read: boolean; write: boolean; execute: boolean };
export type Mode = { owner: Perm; group: Perm; others: Perm; setuid: boolean; setgid: boolean; sticky: boolean };

export const WHO: Who[] = ["owner", "group", "others"];

const digitToPerm = (d: number): Perm => ({ read: (d & 4) !== 0, write: (d & 2) !== 0, execute: (d & 1) !== 0 });
const permToDigit = (p: Perm) => (p.read ? 4 : 0) + (p.write ? 2 : 0) + (p.execute ? 1 : 0);

/** Parses 754, 4755 or rwsr-xr-x. Returns null for anything else. */
export function parseMode(input: string): Mode | null {
  const value = input.trim();
  if (!CHMOD_PATTERN.test(value)) return null;
  if (/^[0-7]+$/.test(value)) {
    const digits = value.padStart(4, "0").split("").map(Number);
    return {
      setuid: (digits[0] & 4) !== 0,
      setgid: (digits[0] & 2) !== 0,
      sticky: (digits[0] & 1) !== 0,
      owner: digitToPerm(digits[1]),
      group: digitToPerm(digits[2]),
      others: digitToPerm(digits[3]),
    };
  }
  const triplet = (s: string, special: string): [Perm, boolean] => [
    { read: s[0] === "r", write: s[1] === "w", execute: s[2] === "x" || s[2] === special },
    s[2].toLowerCase() === special,
  ];
  const [owner, setuid] = triplet(value.slice(0, 3), "s");
  const [group, setgid] = triplet(value.slice(3, 6), "s");
  const [others, sticky] = triplet(value.slice(6, 9), "t");
  return { owner, group, others, setuid, setgid, sticky };
}

export function modeToOctal(m: Mode): string {
  const special = (m.setuid ? 4 : 0) + (m.setgid ? 2 : 0) + (m.sticky ? 1 : 0);
  const base = `${permToDigit(m.owner)}${permToDigit(m.group)}${permToDigit(m.others)}`;
  return special ? `${special}${base}` : base;
}

export function modeToSymbolic(m: Mode): string {
  const triplet = (p: Perm, flag: boolean, letter: string) =>
    `${p.read ? "r" : "-"}${p.write ? "w" : "-"}${flag ? (p.execute ? letter : letter.toUpperCase()) : p.execute ? "x" : "-"}`;
  return triplet(m.owner, m.setuid, "s") + triplet(m.group, m.setgid, "s") + triplet(m.others, m.sticky, "t");
}

// ---------- password entropy ----------

export type EntropyResult = {
  length: number;
  pool: number;
  bits: number;
  classes: { lower: boolean; upper: boolean; digits: boolean; symbols: boolean; other: boolean };
  /** Seconds to try half the space at 10 billion guesses per second (a fast offline attack). */
  crackSeconds: number;
  rating: "very-weak" | "weak" | "fair" | "strong" | "very-strong";
  /** Patterns that make the real strength far lower than the estimate. */
  warnings: ("common" | "repeated" | "sequence")[];
};

const COMMON = new Set([
  "password",
  "123456",
  "12345678",
  "123456789",
  "qwerty",
  "abc123",
  "letmein",
  "iloveyou",
  "admin",
  "welcome",
  "monkey",
  "dragon",
  "football",
  "111111",
  "password1",
  "qwerty123",
]);

function hasSequence(s: string): boolean {
  const lower = s.toLowerCase();
  for (let i = 0; i + 3 < lower.length; i++) {
    const a = lower.charCodeAt(i);
    if (
      [1, 2, 3].every((k) => lower.charCodeAt(i + k) === a + k) ||
      [1, 2, 3].every((k) => lower.charCodeAt(i + k) === a - k)
    ) {
      return true;
    }
  }
  return false;
}

export function entropy(password: string): EntropyResult {
  const chars = [...password];
  const classes = {
    lower: /[a-z]/.test(password),
    upper: /[A-Z]/.test(password),
    digits: /[0-9]/.test(password),
    symbols: /[ !-/:-@[-`{-~]/.test(password),
    other: /[^\x20-\x7e]/.test(password),
  };
  const pool =
    (classes.lower ? 26 : 0) +
    (classes.upper ? 26 : 0) +
    (classes.digits ? 10 : 0) +
    (classes.symbols ? 33 : 0) +
    (classes.other ? 100 : 0);
  const bits = chars.length === 0 || pool === 0 ? 0 : chars.length * Math.log2(pool);

  const warnings: EntropyResult["warnings"] = [];
  if (COMMON.has(password.toLowerCase())) warnings.push("common");
  if (/(.)\1{2,}/u.test(password)) warnings.push("repeated");
  if (hasSequence(password)) warnings.push("sequence");

  const rating: EntropyResult["rating"] =
    warnings.includes("common") || bits < 28
      ? "very-weak"
      : bits < 40
        ? "weak"
        : bits < 60
          ? "fair"
          : bits < 80
            ? "strong"
            : "very-strong";

  return {
    length: chars.length,
    pool,
    bits: Math.round(bits * 10) / 10,
    classes,
    crackSeconds: bits === 0 ? 0 : 2 ** (bits - 1) / 1e10,
    rating,
    warnings,
  };
}

/** A rough human duration ("instantly", "3 hours", "about 40 thousand years"). Returned as a unit + count for i18n. */
export function roughDuration(seconds: number): {
  unit: "instant" | "seconds" | "minutes" | "hours" | "days" | "years" | "eons";
  count: number;
} {
  if (seconds < 1) return { unit: "instant", count: 0 };
  const units = [
    ["seconds", 60],
    ["minutes", 60],
    ["hours", 24],
    ["days", 365],
  ] as const;
  let value = seconds;
  for (const [unit, next] of units) {
    if (value < next) return { unit, count: Math.round(value) };
    value /= next;
  }
  if (value > 1e12) return { unit: "eons", count: 0 };
  return { unit: "years", count: Math.round(value) };
}
