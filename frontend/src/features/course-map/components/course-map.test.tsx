import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { CourseMapData } from "../types";
import { CourseMap } from "./course-map";
import { EDGE_STYLES, EdgePath, type EdgeKind } from "./edge-style";
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
    // The map is one tab stop (the legend's rows after it are their own controls).
    const canvas = screen.getByRole("group", { name: /Course map of/ });
    expect(
      within(canvas)
        .getAllByRole("button")
        .filter((b) => b.tabIndex === 0),
    ).toHaveLength(1);
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
      // A lesson without a shape shows its problem in the node; no shape description, no placeholder hint.
      "Share secrets with strangers.",
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
    renderWithProviders(
      <CourseMapOutline map={{ ...MAP, edges: [...MAP.edges, { from: "crypto", to: "tls", type: "requires" }] }} />,
    );
    const item = (title: string) => screen.getByRole("link", { name: title }).closest("li")!;
    // Stored Encryption → TLS: "TLS requires Encryption", while "Encryption feeds into TLS".
    expect(within(item("TLS")).getByText("requires Encryption")).toBeInTheDocument();
    expect(within(item("Encryption")).getByText("feeds into TLS")).toBeInTheDocument();
    expect(within(item("Encryption")).queryByText(/requires/)).toBeNull();
  });
});

describe("one style map for the map and its legend", () => {
  // What a drawn line looks like, with the arrowheads' document-specific prefix taken off.
  const look = (p: Element) => ({
    stroke: (p as SVGPathElement).style.stroke,
    width: p.getAttribute("stroke-width"),
    dash: p.getAttribute("stroke-dasharray"),
    cap: p.getAttribute("stroke-linecap"),
    start: p.getAttribute("marker-start")?.replace(/url\(#[^-]+-/, "") ?? null,
    end: p.getAttribute("marker-end")?.replace(/url\(#[^-]+-/, "") ?? null,
  });

  it("draws each relation on the map exactly as its legend row shows it, both from EDGE_STYLES", () => {
    const { container } = setup();
    const kinds = [...new Set(MAP.edges.map((e) => e.type))];
    for (const kind of kinds) {
      const onMap = container.querySelector(`g[data-edge] path[data-kind="${kind}"]`)!;
      const inLegend = container.querySelector(`button[data-legend="${kind}"] path[data-kind="${kind}"]`)!;
      expect(onMap, kind).not.toBeNull();
      expect(inLegend, kind).not.toBeNull();
      expect(look(onMap), kind).toEqual(look(inLegend));
      expect(look(onMap).stroke, kind).toBe(EDGE_STYLES[kind].color);
      expect(Number(look(onMap).width), kind).toBe(EDGE_STYLES[kind].width);
    }
  });

  it("tells every kind apart by more than colour: weight, line or arrowheads", () => {
    const cue = (k: EdgeKind) => {
      const s = EDGE_STYLES[k];
      return `${s.width}|${s.line}|${s.start ?? ""}|${s.end ?? ""}|${s.small ? "s" : ""}`;
    };
    const all = Object.keys(EDGE_STYLES) as EdgeKind[];
    expect(new Set(all.map(cue)).size).toBe(all.length);
  });

  it("highlights only a legend row's kind on the map while the row is hovered, and keeps it on click", async () => {
    const user = userEvent.setup();
    const { container } = setup();
    const row = screen.getByRole("button", { name: /feeds into/ });
    const state = (kind: string) => container.querySelector(`g[data-edge$=">${kind}"]`)!.getAttribute("data-state");
    await user.hover(row);
    expect(state("feeds-into")).toBe("emphasis");
    expect(state("contrasts-with")).toBe("dim");
    await user.unhover(row);
    expect(state("contrasts-with")).toBe("normal");
    await user.click(row);
    expect(row).toHaveAttribute("aria-pressed", "true");
    await user.unhover(row);
    expect(state("contrasts-with")).toBe("dim");
    await user.keyboard("{Escape}");
    expect(row).toHaveAttribute("aria-pressed", "false");
  });
});

it("keeps a line's colour when a caller adds its own style (a dimmed part link stays teal)", () => {
  const { container } = renderWithProviders(
    <svg aria-hidden>
      <EdgePath kind="part-link" d="M 0 0 H 10" prefix="t" style={{ opacity: 0.3 }} />
    </svg>,
  );
  const path = container.querySelector("path")!;
  expect(path.style.stroke).toBe(EDGE_STYLES["part-link"].color);
  expect(path.style.opacity).toBe("0.3");
});
