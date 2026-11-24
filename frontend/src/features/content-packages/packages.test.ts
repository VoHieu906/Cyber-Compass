import { afterEach, describe, expect, it, vi } from "vitest";
import { chatbotErrorText } from "./chatbot-text";
import { isStalled, isWorking, pollDelay, POLL } from "./polling";

describe("chatbotErrorText", () => {
  it("names the format and version, lists every error with its place, and ends with the instruction", () => {
    const text = chatbotErrorText({
      errors: {
        "operations.0.key": [
          'No section has the key "the-7-layers". Use a key from the overview exactly; keys are not guessed from headings.',
        ],
        "operations.2.section.payload.text": ["This can not be empty.", "Use at most 20000 characters."],
      },
    });

    expect(text).toBe(
      [
        'CyberCompass content package (format "cybercompass.content", version 1): 3 errors.',
        '- operations.0.key: No section has the key "the-7-layers". Use a key from the overview exactly; keys are not guessed from headings.',
        "- operations.2.section.payload.text: This can not be empty.",
        "- operations.2.section.payload.text: Use at most 20000 characters.",
        "",
        "Fix only these errors, keep every uuid and key unchanged, and return the complete package as a single JSON document.",
      ].join("\n"),
    );
  });

  it("says one error in the singular and keeps text exactly as the server wrote it", () => {
    const text = chatbotErrorText({ errors: { package: ["This is not valid JSON (<b>Syntax error</b>)."] } });

    expect(text.split("\n")[0]).toContain(": 1 error.");
    expect(text).toContain("- package: This is not valid JSON (<b>Syntax error</b>).");
  });
});

describe("chatbotErrorText with many errors", () => {
  it("keeps the first 100 lines and counts the rest, including those the API left out", () => {
    const errors = Object.fromEntries(
      Array.from({ length: 105 }, (_, i) => [`operations.${i}.key`, ["No section has this key."]]),
    );
    const lines = chatbotErrorText({ errors, more_errors: 20 }).split("\n");

    expect(lines[0]).toContain(": 125 errors.");
    expect(lines.filter((l) => l.startsWith("- "))).toHaveLength(100);
    expect(lines[101]).toBe("…and 25 more errors. Fix these first; checking again will list the rest.");
    expect(lines.at(-1)).toContain("return the complete package as a single JSON document");
  });
});

describe("polling", () => {
  afterEach(() => vi.useRealTimers());

  it("backs off from 1 second to at most 5", () => {
    expect([0, 1, 2, 3, 4, 5, 20].map(pollDelay)).toEqual([1000, 1500, 2250, 3375, 5000, 5000, 5000]);
  });

  it("polls only while the worker has something to finish", () => {
    expect(["validating", "applying", "exporting"].every((s) => isWorking(s as never))).toBe(true);
    expect(["uploading", "ready", "applied", "failed"].some((s) => isWorking(s as never))).toBe(false);
  });

  it("calls a step stalled once it has waited in one working status for 30 seconds", () => {
    const since = 1_000_000;
    expect(isStalled("validating", since, since + POLL.stalledMs - 1)).toBe(false);
    expect(isStalled("validating", since, since + POLL.stalledMs)).toBe(true);
    expect(isStalled("ready", since, since + 10 * POLL.stalledMs)).toBe(false);
  });
});
