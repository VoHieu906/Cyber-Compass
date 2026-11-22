import { act, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { CompassPageProvider } from "@/features/compass/page-items";
import type { LessonSection } from "@/features/learning/types";
import { renderWithProviders } from "@/test/render";
import type { Post } from "../types";
import { PostCard } from "./post-card";
import { PostReader } from "./post-reader";

/** A long post: 150 blocks, a heading every third block, callouts (never in the list) and graphs. */
function longPost(): Post {
  const sections = Array.from({ length: 150 }, (_, i): LessonSection => {
    if (i % 25 === 24) {
      return {
        id: `s-${i}`,
        type: "flow",
        heading: `Graph ${i}`,
        payload: {
          nodes: [
            { id: "a", label: "Build" },
            { id: "b", label: "Run" },
          ],
          edges: [{ from: "a", to: "b" }],
        },
      } as unknown as LessonSection;
    }
    if (i % 10 === 5)
      return {
        id: `s-${i}`,
        type: "callout",
        heading: `Note ${i}`,
        payload: { text: "Mind the socket.", tone: "warning" },
      };
    return {
      id: `s-${i}`,
      type: "prose",
      heading: i % 3 === 0 ? `Part ${i}` : null,
      payload: { text: `Paragraph ${i} about containers.` },
    };
  });
  return {
    slug: "docker-containers-101",
    title: "Docker & Containers 101",
    summary: "What a container really is.",
    tags: ["docker", "linux"],
    estimated_minutes: 42,
    published_at: "2026-11-22T00:00:00Z",
    updated_at: "2026-11-22T00:00:00Z",
    author: "Ada Admin",
    cover: {
      id: "c",
      url: "http://localhost:8000/storage/lesson-media/c.webp",
      alt: "Containers on one kernel",
      width: 1600,
      height: 640,
    },
    sections,
  };
}

let observed: { callback: IntersectionObserverCallback; targets: Element[] }[] = [];

beforeEach(() => {
  observed = [];
  // Nothing scrolls in jsdom: blocks below the fold mount only when the observer says so.
  vi.stubGlobal(
    "IntersectionObserver",
    class {
      entry: { callback: IntersectionObserverCallback; targets: Element[] };
      constructor(callback: IntersectionObserverCallback) {
        this.entry = { callback, targets: [] };
        observed.push(this.entry);
      }
      observe(el: Element) {
        this.entry.targets.push(el);
      }
      disconnect() {}
      unobserve() {}
    },
  );
});

afterEach(() => vi.unstubAllGlobals());

describe("post reader", () => {
  it("lists every heading of a long post in the table of contents, except callouts", () => {
    const post = longPost();
    renderWithProviders(
      <CompassPageProvider>
        <PostReader post={post} />
      </CompassPageProvider>,
    );

    const toc = screen.getByRole("navigation", { name: "On this page" });
    const links = within(toc).getAllByRole("link");
    const headed = post.sections.filter((s) => s.heading && s.type !== "callout");
    expect(links).toHaveLength(headed.length);
    expect(links[0]).toHaveAttribute("href", "#s-0");
    expect(links.map((l) => l.textContent)).not.toContain("Note 5");
    expect(screen.getByRole("heading", { level: 1, name: "Docker & Containers 101" })).toBeInTheDocument();
    expect(screen.getByRole("img", { name: "Containers on one kernel" })).toBeInTheDocument();
  });

  it("gives each heading a copy-link button that copies the section address", async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    const user = userEvent.setup();
    Object.defineProperty(navigator, "clipboard", { value: { writeText }, configurable: true });
    renderWithProviders(<PostReader post={longPost()} />);

    await user.click(screen.getByRole("button", { name: "Copy a link to “Part 3”" }));

    expect(writeText).toHaveBeenCalledWith(expect.stringMatching(/#s-3$/));
    // The button sits beside the heading: the heading's accessible name is only its text.
    expect(screen.getByRole("heading", { level: 2, name: "Part 3" }).textContent).toBe("Part 3");
    expect(window.location.hash).toBe("#s-3");
  });

  it("mounts graphs only when they come near the viewport, behind a same-size placeholder", () => {
    renderWithProviders(<PostReader post={longPost()} />);
    const graph = document.getElementById("s-24")!;

    expect(within(graph).queryByRole("img")).not.toBeInTheDocument();
    const placeholder = graph.querySelector("[aria-hidden].aspect-\\[16\\/10\\]");
    expect(placeholder).not.toBeNull();

    const watcher = observed.find((o) => o.targets.includes(placeholder!))!;
    act(() =>
      watcher.callback(
        [{ isIntersecting: true, target: placeholder! } as IntersectionObserverEntry],
        {} as IntersectionObserver,
      ),
    );

    expect(graph.querySelector("[aria-hidden].aspect-\\[16\\/10\\]")).toBeNull();
    expect(graph.querySelector("svg")).not.toBeNull();
  });
});

describe("post card", () => {
  it("links the title to the post and each tag to the filtered blog", () => {
    renderWithProviders(<PostCard post={longPost()} />);

    expect(screen.getByRole("link", { name: "Docker & Containers 101" })).toHaveAttribute(
      "href",
      "/blog/docker-containers-101",
    );
    expect(screen.getByRole("link", { name: "#docker" })).toHaveAttribute("href", "/blog?tag=docker");
    expect(screen.getByText("42 min")).toBeInTheDocument();
  });
});
