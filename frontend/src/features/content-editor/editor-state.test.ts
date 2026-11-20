import { describe, expect, it } from "vitest";
import { errorAt, errorsUnder, moveItem, newSection, parseJsonObject, sameCopy } from "./editor-state";
import { DEFAULT_LESSON_QUERY, lessonQueryToApi, lessonQueryToSearch, parseLessonQuery, slugify } from "./query-state";
import type { BlockTypeInfo, WorkingCopy } from "./types";

const copy: WorkingCopy = {
  chapter_id: 1,
  slug: "a",
  title: "A",
  summary: "",
  estimated_minutes: 10,
  objectives: [],
  key_takeaways: [],
  resources: [],
  sections: [{ id: "x", type: "prose", heading: null, payload: { text: "Hi" } }],
};

describe("server errors", () => {
  const errors = {
    title: ["Required"],
    "sections.2.payload.text": ["Too short"],
    "sections.2.payload": ["Bad"],
    "sections.12.type": ["?"],
  };

  it("finds an exact key", () => {
    expect(errorAt(errors, "title")).toBe("Required");
    expect(errorAt(errors, "summary")).toBeUndefined();
  });

  it("groups errors under a block without matching neighbouring indexes", () => {
    expect(errorsUnder(errors, "sections.2.payload")).toEqual({ "": ["Bad"], text: ["Too short"] });
    expect(errorsUnder(errors, "sections.1")).toEqual({});
  });
});

describe("editing helpers", () => {
  it("moves items and ignores impossible moves", () => {
    expect(moveItem(["a", "b", "c"], 0, 2)).toEqual(["b", "c", "a"]);
    expect(moveItem(["a", "b"], 1, 5)).toEqual(["a", "b"]);
  });

  it("starts a new block from the type's example, except images", () => {
    const callout = { key: "callout", example: { text: "Note", tone: "tip" } } as unknown as BlockTypeInfo;
    const section = newSection(callout);
    expect(section).toEqual({ type: "callout", heading: null, payload: { text: "Note", tone: "tip" } });
    section.payload.text = "changed";
    expect(callout.example.text).toBe("Note"); // a copy, not the shared example

    expect(newSection({ key: "media", example: { media: "fake" } } as unknown as BlockTypeInfo).payload).toEqual({
      media: "",
      caption: null,
    });
  });

  it("compares working copies regardless of key order", () => {
    const reordered = { ...copy, sections: [{ payload: { text: "Hi" }, heading: null, type: "prose", id: "x" }] };
    expect(sameCopy(copy, reordered)).toBe(true);
    expect(sameCopy(copy, { ...copy, title: "B" })).toBe(false);
  });

  it("parses JSON objects only, reporting errors instead of throwing", () => {
    expect(parseJsonObject('{"a":1}')).toEqual({ ok: true, value: { a: 1 } });
    expect(parseJsonObject("[1]").ok).toBe(false);
    expect(parseJsonObject("{oops").ok).toBe(false);
  });
});

describe("lesson list query", () => {
  it("round-trips through the URL and maps to API filters", () => {
    const query = parseLessonQuery(new URLSearchParams("q=tls&course=3&status=draft&drafts=1&sort=course&page=2"));
    expect(query).toEqual({ q: "tls", course: 3, status: "draft", drafts: true, sort: "course", page: 2 });
    expect(lessonQueryToSearch(query)).toBe("?q=tls&course=3&status=draft&drafts=1&sort=course&page=2");
    expect(lessonQueryToApi(query)).toMatchObject({
      "filter[course]": 3,
      "filter[status]": "draft",
      "filter[has_draft]": 1,
    });
    expect(parseLessonQuery(new URLSearchParams("status=gone&sort=x&course=-1"))).toEqual(DEFAULT_LESSON_QUERY);
  });

  it("makes URL slugs, including from Vietnamese titles", () => {
    expect(slugify("  How TLS protects a connection! ")).toBe("how-tls-protects-a-connection");
    expect(slugify("Bảo mật đường truyền")).toBe("bao-mat-duong-truyen");
  });
});
