import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { CourseMapData } from "../types";
import { CourseMap } from "./course-map";
import { CourseMapOutline } from "./outline";

vi.mock("@/features/catalog/hooks", () => ({
  useCourseProgress: () => ({ data: { lessons: { hashing: "completed", tls: "started" } } }),
}));

const SECTION = "6f1c2a9e-3b7d-4e0a-8c5f-9d2e4b7a1c30";

const MAP: CourseMapData = {
  problem: "Keep data secret and unaltered.",
  layout: "auto",
  lanes: [],
  lessons: [
    {
      slug: "hashing",
      title: "Hashing",
      placement: null,
      problem: "Store passwords safely.",
      shape: "pipeline",
      rows: null,
      cols: null,
      edges: [],
      nodes: [
        { id: "salt", label: "Unique salt", note: "Random per user.", section: SECTION },
        { id: "slow", label: "Slow hash", lesson: "crypto" },
      ],
    },
    {
      slug: "crypto",
      title: "Encryption",
      placement: null,
      problem: "Share secrets with strangers.",
      shape: null,
      rows: null,
      cols: null,
      nodes: [],
      edges: [],
    },
    {
      slug: "tls",
      title: "TLS",
      placement: null,
      problem: "Talk privately over a watched network.",
      shape: null,
      rows: null,
      cols: null,
      nodes: [],
      edges: [],
    },
    {
      slug: "aside",
      title: "History",
      placement: null,
      problem: null,
      shape: null,
      rows: null,
      cols: null,
      nodes: [],
      edges: [],
    },
  ],
  edges: [
    { from: "hashing", to: "crypto", type: "contrasts-with", label: "one-way or keyed" },
    { from: "crypto", to: "tls", type: "feeds-into" },
  ],
};

const setup = () =>
  renderWithProviders(<CourseMap map={MAP} courseSlug="applied" courseTitle="Applied cryptography" />);
const node = (name: string) => screen.getByRole("button", { name });

describe("CourseMap", () => {
  it("shows every lesson as a button with its problem, shape and status, behind one tab stop", async () => {
    setup();
    expect(node("Hashing")).toHaveAccessibleDescription("Store passwords safely.. Shape: Pipeline. Completed");
    expect(node("TLS")).toHaveAccessibleDescription(/In progress/);
    expect(screen.getAllByRole("button").filter((b) => b.tabIndex === 0)).toHaveLength(1);
    expect(screen.getByRole("group", { name: "The big problem" })).toHaveTextContent("Keep data secret");
    expect(screen.getByText("contrasts with")).toBeInTheDocument(); // legend
  });

  it("follows relations with the arrow keys and reaches the goal", async () => {
    const user = userEvent.setup();
    setup();
    node("Hashing").focus();
    await user.keyboard("{ArrowRight}");
    expect(node("Encryption")).toHaveFocus();
    await user.keyboard("{ArrowRight}");
    expect(node("TLS")).toHaveFocus();
    await user.keyboard("{ArrowLeft}");
    expect(node("Encryption")).toHaveFocus();
    await user.keyboard("{ArrowDown}{ArrowDown}{ArrowDown}");
    expect(screen.getByRole("group", { name: "The big problem" })).toHaveFocus();
  });

  it("opens a lesson's architecture, dims the unrelated lessons and returns with Escape", async () => {
    const user = userEvent.setup();
    setup();
    node("Hashing").focus();
    await user.keyboard("{Enter}");

    const panel = await screen.findByRole("region", { name: "Hashing: how it is built" });
    expect(node("Hashing")).toHaveAttribute("aria-expanded", "true");
    expect(node("Encryption")).toHaveAttribute("data-state", "related");
    expect(node("TLS")).toHaveAttribute("data-state", "dimmed");
    expect(node("History")).toHaveAttribute("data-state", "dimmed");
    expect(within(panel).getByRole("link", { name: /Open lesson/ })).toHaveAttribute("href", "/learn/hashing");
    expect(screen.getByText("one-way or keyed")).toBeInTheDocument();

    await user.click(within(panel).getByRole("button", { name: "Unique salt" }));
    expect(within(panel).getByText(/Random per user/)).toBeInTheDocument();
    expect(within(panel).getByRole("link", { name: /Read this in the lesson/ })).toHaveAttribute(
      "href",
      `/learn/hashing#${SECTION}`,
    );

    within(panel).getByRole("button", { name: "Unique salt" }).focus();
    await user.keyboard("{ArrowRight}");
    expect(within(panel).getByRole("button", { name: "Slow hash" })).toHaveFocus();

    await user.keyboard("{Escape}");
    await waitFor(() => expect(node("Hashing")).toHaveFocus());
    expect(screen.queryByRole("region")).not.toBeInTheDocument();
    expect(node("TLS")).toHaveAttribute("data-state", "normal");
  });

  it("moves focus straight to a lesson a part links to", async () => {
    const user = userEvent.setup();
    setup();
    await user.click(node("Hashing"));
    const panel = await screen.findByRole("region", { name: "Hashing: how it is built" });
    await user.click(within(panel).getByRole("button", { name: "Slow hash" }));
    await user.click(within(panel).getByRole("button", { name: "Go to Encryption" }));
    expect(await screen.findByRole("region", { name: "Encryption: how it is built" })).toHaveTextContent(
      "no particular shape",
    );
    expect(node("Hashing")).toHaveAttribute("data-state", "related");
  });
});

describe("CourseMapOutline", () => {
  it("lists the problem, each lesson's relations and its architecture with section links", () => {
    renderWithProviders(<CourseMapOutline map={MAP} />);
    expect(screen.getByText("Keep data secret and unaltered.")).toBeInTheDocument();
    expect(screen.getByText(/contrasts with Encryption \(one-way or keyed\)/)).toBeInTheDocument();
    expect(screen.getByText(/Built as: Pipeline/)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Unique salt" })).toHaveAttribute("href", `/learn/hashing#${SECTION}`);
    expect(screen.getByText(/Go to Encryption/)).toBeInTheDocument();
  });

  it("reads every relation as a sentence about the lesson it is listed under", () => {
    renderWithProviders(<CourseMapOutline map={{ ...MAP, edges: [...MAP.edges, { from: "crypto", to: "tls", type: "requires" }] }} />);
    const item = (title: string) => screen.getByRole("link", { name: title }).closest("li")!;
    // Stored Encryption → TLS: "TLS requires Encryption", while "Encryption feeds into TLS".
    expect(within(item("TLS")).getByText("requires Encryption")).toBeInTheDocument();
    expect(within(item("Encryption")).getByText("feeds into TLS")).toBeInTheDocument();
    expect(within(item("Encryption")).queryByText(/requires/)).toBeNull();
  });
});
