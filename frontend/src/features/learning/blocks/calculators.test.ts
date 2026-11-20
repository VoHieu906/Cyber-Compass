import { describe, expect, it } from "vitest";
import { cidr, entropy, modeToOctal, modeToSymbolic, parseMode, roughDuration } from "./calculators";

describe("cidr", () => {
  it("computes a /26", () => {
    expect(cidr("192.168.1.130/26")).toMatchObject({
      mask: "255.255.255.192",
      wildcard: "0.0.0.63",
      network: "192.168.1.128",
      broadcast: "192.168.1.191",
      firstHost: "192.168.1.129",
      lastHost: "192.168.1.190",
      totalAddresses: 64,
      usableHosts: 62,
      maskBits: "11111111.11111111.11111111.11000000",
    });
  });

  it("handles the edges: /0, /31 and /32", () => {
    expect(cidr("10.1.2.3/0")).toMatchObject({
      network: "0.0.0.0",
      broadcast: "255.255.255.255",
      usableHosts: 2 ** 32 - 2,
    });
    expect(cidr("10.0.0.7/31")).toMatchObject({
      network: "10.0.0.6",
      firstHost: "10.0.0.6",
      lastHost: "10.0.0.7",
      usableHosts: 2,
    });
    expect(cidr("10.0.0.7/32")).toMatchObject({ network: "10.0.0.7", usableHosts: 1, mask: "255.255.255.255" });
  });

  it("refuses what is not an address with a prefix", () => {
    for (const bad of ["10.0.0.1", "10.0.0.256/8", "10.0.0.1/33", "a.b.c.d/8", ""]) expect(cidr(bad)).toBeNull();
  });
});

describe("chmod", () => {
  it("round-trips octal and symbolic modes", () => {
    const m = parseMode("754")!;
    expect(modeToSymbolic(m)).toBe("rwxr-xr--");
    expect(modeToOctal(parseMode("rwxr-xr--")!)).toBe("754");
  });

  it("understands setuid, setgid and sticky bits", () => {
    expect(modeToSymbolic(parseMode("4755")!)).toBe("rwsr-xr-x");
    expect(modeToSymbolic(parseMode("1777")!)).toBe("rwxrwxrwt");
    expect(modeToSymbolic(parseMode("2640")!)).toBe("rw-r-S---");
    expect(modeToOctal(parseMode("rwsr-xr-T")!)).toBe("5754");
    expect(modeToOctal(parseMode("0644")!)).toBe("644");
  });

  it("refuses anything else", () => {
    for (const bad of ["778", "rwxrwx", "75", "rwxrwxrwz"]) expect(parseMode(bad)).toBeNull();
  });
});

describe("entropy", () => {
  it("scales with length and character variety", () => {
    expect(entropy("").bits).toBe(0);
    expect(entropy("abcdefgh").pool).toBe(26);
    expect(entropy("Abcdefg1!").pool).toBe(95);
    expect(entropy("correct horse battery staple").bits).toBeGreaterThan(entropy("Tr0ub4dor&3").bits);
  });

  it("flags common passwords, repeats and sequences whatever the estimate says", () => {
    expect(entropy("Password").warnings).toEqual(["common"]);
    expect(entropy("Password").rating).toBe("very-weak");
    expect(entropy("aaaa-long-enough").warnings).toContain("repeated");
    expect(entropy("xx1234yy").warnings).toContain("sequence");
    expect(entropy("q7#Lp9!zR2@w").warnings).toEqual([]);
  });

  it("describes crack times roughly", () => {
    expect(roughDuration(0.2)).toEqual({ unit: "instant", count: 0 });
    expect(roughDuration(90)).toEqual({ unit: "minutes", count: 2 });
    expect(roughDuration(3 * 3600)).toEqual({ unit: "hours", count: 3 });
    expect(roughDuration(1e30).unit).toBe("eons");
  });
});
