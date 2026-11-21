import { describe, expect, it } from "vitest";
import { contentSecurityPolicy, originOf } from "./csp";

const directives = (policy: string) => Object.fromEntries(policy.split("; ").map((d) => [d.split(" ")[0], d]));

describe("content security policy", () => {
  it("runs scripts only with the nonce, with no inline or eval in production", () => {
    const policy = directives(contentSecurityPolicy({ nonce: "abc", dev: false, apiOrigin: "https://api.example.org" }));
    expect(policy["script-src"]).toBe("script-src 'self' 'nonce-abc' 'strict-dynamic'");
    expect(policy["object-src"]).toBe("object-src 'none'");
    expect(policy["frame-ancestors"]).toBe("frame-ancestors 'none'");
    expect(policy["base-uri"]).toBe("base-uri 'self'");
  });

  it("allows eval only for the development server", () => {
    const policy = directives(contentSecurityPolicy({ nonce: "abc", dev: true, apiOrigin: "http://localhost:8000" }));
    expect(policy["script-src"]).toContain("'unsafe-eval'");
  });

  it("lets the app reach the API and show uploads from the media origin", () => {
    const policy = directives(
      contentSecurityPolicy({ nonce: "n", dev: false, apiOrigin: "https://api.example.org", mediaOrigin: "https://media.example.org" }),
    );
    expect(policy["connect-src"]).toBe("connect-src 'self' https://api.example.org");
    expect(policy["img-src"]).toBe("img-src 'self' blob: data: https://api.example.org https://media.example.org");
    expect(policy["frame-src"]).toBe("frame-src 'self'");
  });

  it("upgrades insecure requests only when the API itself is on HTTPS", () => {
    expect(contentSecurityPolicy({ nonce: "n", dev: false, apiOrigin: "https://api.example.org" })).toContain(
      "upgrade-insecure-requests",
    );
    expect(contentSecurityPolicy({ nonce: "n", dev: true, apiOrigin: "http://localhost:8000" })).not.toContain(
      "upgrade-insecure-requests",
    );
  });

  it("reads origins from URLs and ignores anything else", () => {
    expect(originOf("https://media.example.org/lesson-media/a.webp")).toBe("https://media.example.org");
    expect(originOf("not a url")).toBeUndefined();
    expect(originOf(undefined)).toBeUndefined();
  });
});
