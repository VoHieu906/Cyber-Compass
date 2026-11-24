import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api/client";
import { renderWithProviders } from "@/test/render";
import type { AdminCourseGraph, AdminLesson, WorkingCopy } from "../types";
import { ArchitecturePanel } from "./architecture-panel";
import { CourseGraphPanel } from "./course-graph-panel";

const mocks = vi.hoisted(() => ({ fetchCourseGraph: vi.fn(), saveCourseGraph: vi.fn(), restoreCourseGraph: vi.fn() }));
vi.mock("../api", () => ({
  lessonApi: {},
  postApi: {},
  fetchCourses: async () => [
    {
      id: 1,
      slug: "crypto",
      title: "Crypto",
      is_published: true,
      chapters: [
        {
          id: 2,
          title: "Basics",
          position: 1,
          lessons: [
            { id: 7, slug: "hashing", title: "Hashing", status: "published", has_unpublished_changes: false },
            { id: 8, slug: "tls", title: "TLS", status: "published", has_unpublished_changes: false },
          ],
        },
      ],
    },
  ],
  fetchCourseGraph: (...a: unknown[]) => mocks.fetchCourseGraph(...a),
  saveCourseGraph: (...a: unknown[]) => mocks.saveCourseGraph(...a),
  restoreCourseGraph: (...a: unknown[]) => mocks.restoreCourseGraph(...a),
}));
vi.mock("@/features/catalog/hooks", () => ({ useCourseProgress: () => ({ data: undefined }) }));

const S1 = "11111111-1111-4111-8111-111111111111";
const S2 = "22222222-2222-4222-8222-222222222222";

const copy: WorkingCopy = {
  chapter_id: 2,
  slug: "hashing",
  title: "Hashing",
  summary: "",
  estimated_minutes: 10,
  objectives: [],
  key_takeaways: [],
  resources: [],
  architecture: null,
  sections: [
    { id: S1, type: "prose", heading: "What a hash does", payload: { text: "x" } },
    {
      id: S2,
      type: "layer-stack",
      heading: "The stack",
      payload: {
        layers: [
          { name: "Top", description: "First." },
          { name: "Bottom", description: "Second." },
        ],
      },
    },
  ],
};
const lesson = {
  id: 7,
  course: { id: 1, slug: "crypto", title: "Crypto", is_published: true },
  architecture_issues: [],
  architecture_suggestions: [],
} as unknown as AdminLesson;

function Harness({
  initial,
  errors = {},
  suggestions = [],
}: {
  initial: WorkingCopy;
  errors?: Record<string, string[]>;
  suggestions?: AdminLesson["architecture_suggestions"];
}) {
  const [state, setState] = useState(initial);
  return (
    <>
      <ArchitecturePanel
        copy={state}
        lesson={{ ...lesson, architecture_suggestions: suggestions }}
        errors={errors}
        onChange={(patch) => setState((c) => ({ ...c, ...patch }))}
      />
      <output data-testid="arch">{JSON.stringify(state.architecture)}</output>
    </>
  );
}
const arch = () => JSON.parse(screen.getByTestId("arch").textContent!);

describe("ArchitecturePanel", () => {
  it("starts an architecture, picks a shape, adds parts linked to blocks and previews them", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Harness initial={copy} />);
    await user.click(screen.getByRole("button", { name: "Describe the architecture" }));
    await user.type(screen.getByLabelText("Problem it solves"), "Store passwords safely.");
    await user.selectOptions(screen.getByLabelText("Shape"), "pipeline");
    await user.click(screen.getByRole("button", { name: "Add a part" }));
    await user.click(screen.getByRole("button", { name: "Add a part" }));
    const [first] = screen.getAllByLabelText("Block that teaches it");
    await user.selectOptions(first, S1);

    expect(arch()).toMatchObject({
      version: 1,
      problem: "Store passwords safely.",
      shape: "pipeline",
      nodes: [{ id: "part-1", section: S1 }, { id: "part-2" }],
    });
    expect(screen.getByRole("group", { name: "Preview" })).toBeInTheDocument();
  });

  it("fills the parts from a block of the lesson, each linked back to it", async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <Harness initial={{ ...copy, architecture: { version: 1, problem: "P", shape: null, nodes: [], edges: [] } }} />,
    );
    await user.selectOptions(screen.getByLabelText("Fill from a block"), S2);
    await user.click(screen.getByRole("button", { name: "Fill" }));
    expect(arch()).toMatchObject({
      shape: "layers",
      nodes: [
        { label: "Top", section: S2 },
        { label: "Bottom", section: S2 },
      ],
    });
  });

  it("flags a part whose block was removed, and shows server errors at the part", () => {
    renderWithProviders(
      <Harness
        initial={{
          ...copy,
          sections: [copy.sections[1]],
          architecture: {
            version: 1,
            problem: "P",
            shape: "pipeline",
            nodes: [
              { id: "a", label: "A", section: S1 },
              { id: "b", label: "B" },
            ],
            edges: [],
          },
        }}
        errors={{ "architecture.nodes.1.label": ["Use at most 40 characters."] }}
      />,
    );
    expect(screen.getByText(/no longer in the lesson/)).toBeInTheDocument();
    expect(screen.getByText("Use at most 40 characters.")).toBeInTheDocument();
  });
});

const panel = (over: Partial<AdminCourseGraph> = {}): AdminCourseGraph => ({
  course: { id: 1, slug: "crypto", title: "Crypto" },
  graph: {
    version: 1,
    problem: "Keep data safe.",
    layout: "auto",
    edges: [{ from: "hashing", to: "tls", type: "feeds-into" }],
  },
  lock_version: 3,
  owned_by_admin: true,
  lessons: [
    {
      id: 7,
      slug: "hashing",
      title: "Hashing",
      status: "published",
      problem: "Store passwords.",
      shape: null,
      architecture: null,
    },
    { id: 8, slug: "tls", title: "TLS", status: "published", problem: null, shape: null, architecture: null },
  ],
  issues: [{ type: "no_problem", lesson: "tls" }],
  suggestions: [],
  history: [{ id: 41, action: "course_graph.saved", at: "2026-11-23T10:00:00Z", actor: "Ada", has_before: true }],
  ...over,
});

describe("CourseGraphPanel", () => {
  beforeEach(() => {
    mocks.fetchCourseGraph.mockReset().mockResolvedValue(panel());
    mocks.saveCourseGraph.mockReset();
    mocks.restoreCourseGraph.mockReset();
  });

  it("lists issues, previews the map and saves with the loaded lock", async () => {
    const user = userEvent.setup();
    mocks.saveCourseGraph.mockImplementation(async (_id: number, graph: unknown) =>
      panel({ graph: graph as AdminCourseGraph["graph"], lock_version: 4 }),
    );
    renderWithProviders(<CourseGraphPanel courseId={1} />);

    expect(await screen.findByText(/TLS has no problem written yet/)).toBeInTheDocument();
    expect(screen.getByRole("group", { name: "Course map of Crypto" })).toBeInTheDocument();
    await user.clear(screen.getByRole("textbox", { name: "The big problem" }));
    await user.type(screen.getByRole("textbox", { name: "The big problem" }), "Protect data.");
    await user.click(screen.getByRole("button", { name: "Save the course map" }));

    await waitFor(() =>
      expect(mocks.saveCourseGraph).toHaveBeenCalledWith(1, expect.objectContaining({ problem: "Protect data." }), 3),
    );
    expect(await screen.findByText("Course map saved.")).toBeInTheDocument();
  });

  it("says when someone else saved first, and restores from history", async () => {
    const user = userEvent.setup();
    mocks.saveCourseGraph.mockRejectedValue(new ApiError(409, { message: "Conflict", code: "conflict" }));
    mocks.restoreCourseGraph.mockResolvedValue(panel({ lock_version: 4 }));
    renderWithProviders(<CourseGraphPanel courseId={1} />);

    await user.click(await screen.findByRole("button", { name: "Save the course map" }));
    expect(await screen.findByText(/Someone else changed this course map/)).toBeInTheDocument();

    const history = screen.getByRole("heading", { name: "History" }).parentElement!;
    await user.click(within(history).getByRole("button", { name: "Restore the version before" }));
    await waitFor(() => expect(mocks.restoreCourseGraph).toHaveBeenCalledWith(1, 41, "before", 3));
  });

  it("offers to create a map when the course has none", async () => {
    mocks.fetchCourseGraph.mockResolvedValue(panel({ graph: null, lock_version: 0, history: [], issues: [] }));
    const user = userEvent.setup();
    renderWithProviders(<CourseGraphPanel courseId={1} />);
    await user.click(await screen.findByRole("button", { name: "Create the course map" }));
    expect(screen.getByRole("textbox", { name: "The big problem" })).toHaveValue("");
  });
});


describe("course map suggestions (F4 Part C)", () => {
  const loose = (over: Partial<AdminCourseGraph> = {}) =>
    panel({
      graph: { version: 1, problem: "Keep data safe.", layout: "auto", edges: [] },
      issues: [
        { type: "unconnected", lesson: "hashing" },
        { type: "unconnected", lesson: "tls" },
        { type: "no_problem", lesson: "tls" },
      ],
      suggestions: [
        {
          key: "relation:hashing",
          type: "relation",
          lesson: "hashing",
          resolves: "unconnected",
          edge: { from: "hashing", to: "tls", type: "builds-on" },
        },
        { key: "problem:tls", type: "problem", lesson: "tls", resolves: "no_problem", problem: "Know what the padlock means.", draft: true },
      ],
      ...over,
    });
  const preview = () => screen.getByRole("heading", { name: /^Preview \(published/ }).parentElement!;

  beforeEach(() => {
    window.localStorage.clear();
    mocks.saveCourseGraph
      .mockReset()
      .mockImplementation(async (_id: number, graph: unknown) =>
        loose({ graph: graph as AdminCourseGraph["graph"], issues: [], suggestions: [] }),
      );
  });

  it("accepts a relation into the form and the live preview, and saves it only on Save", async () => {
    mocks.fetchCourseGraph.mockReset().mockResolvedValue(loose());
    const user = userEvent.setup();
    renderWithProviders(<CourseGraphPanel courseId={1} />);

    expect(await screen.findByText("Suggested: TLS builds on Hashing.")).toBeInTheDocument();
    expect(within(preview()).queryByText("builds on")).toBeNull();
    await user.click(screen.getByRole("button", { name: "Accept: Suggested: TLS builds on Hashing." }));

    expect(screen.getByText("Accepted. Save the map to keep it.")).toBeInTheDocument();
    expect(within(preview()).getByText("builds on")).toBeInTheDocument();
    expect(mocks.saveCourseGraph).not.toHaveBeenCalled();
    await user.click(screen.getByRole("button", { name: "Save the course map" }));
    await waitFor(() =>
      expect(mocks.saveCourseGraph).toHaveBeenCalledWith(
        1,
        expect.objectContaining({ edges: [{ from: "hashing", to: "tls", type: "builds-on" }] }),
        3,
      ),
    );
  });

  it("links a problem suggestion to the lesson editor, and remembers a dismissal", async () => {
    mocks.fetchCourseGraph.mockReset().mockResolvedValue(loose());
    const user = userEvent.setup();
    const first = renderWithProviders(<CourseGraphPanel courseId={1} />);

    const text = await screen.findByText(/Suggested problem, a draft/);
    expect(within(text.closest("li")!).getByRole("link", { name: "Open in the lesson editor" })).toHaveAttribute(
      "href",
      "/admin/lessons/8#architecture-heading",
    );
    await user.click(screen.getByRole("button", { name: /^Dismiss: Suggested problem/ }));
    expect(screen.queryByText(/Suggested problem, a draft/)).toBeNull();
    first.unmount();

    renderWithProviders(<CourseGraphPanel courseId={1} />);
    await screen.findByText("Suggested: TLS builds on Hashing.");
    expect(screen.queryByText(/Suggested problem, a draft/)).toBeNull();
    await user.click(screen.getByRole("button", { name: "Show 1 dismissed suggestion" }));
    expect(screen.getByText(/Suggested problem, a draft/)).toBeInTheDocument();
  });

  it("accepts all places at once, skipping a cell that is already taken", async () => {
    mocks.fetchCourseGraph.mockReset().mockResolvedValue(
      loose({
        graph: {
          version: 1,
          problem: "Keep data safe.",
          layout: "manual",
          edges: [{ from: "hashing", to: "tls", type: "requires" }],
          placement: {},
        },
        issues: [
          { type: "unplaced", lesson: "hashing" },
          { type: "unplaced", lesson: "tls" },
        ],
        suggestions: [
          { key: "placement:hashing", type: "placement", lesson: "hashing", resolves: "unplaced", placement: { col: 0, row: 0 } },
          { key: "placement:tls", type: "placement", lesson: "tls", resolves: "unplaced", placement: { col: 0, row: 0 } },
        ],
      }),
    );
    const user = userEvent.setup();
    renderWithProviders(<CourseGraphPanel courseId={1} />);

    await user.click(await screen.findByRole("button", { name: "Accept all 2 places" }));
    await user.click(screen.getByRole("button", { name: "Save the course map" }));
    await waitFor(() =>
      expect(mocks.saveCourseGraph).toHaveBeenCalledWith(
        1,
        expect.objectContaining({ placement: { hashing: { col: 0, row: 0 } } }),
        3,
      ),
    );
  });

  it("starts a map from the course structure without saving it", async () => {
    mocks.fetchCourseGraph.mockReset().mockResolvedValue(
      loose({
        graph: null,
        lock_version: 0,
        issues: [],
        history: [],
        suggestions: [
          {
            key: "start_map",
            type: "start_map",
            graph: {
              version: 1,
              problem: "Crypto, step by step.",
              layout: "auto",
              edges: [{ from: "hashing", to: "tls", type: "builds-on" }],
            },
          },
        ],
      }),
    );
    const user = userEvent.setup();
    renderWithProviders(<CourseGraphPanel courseId={1} />);

    await user.click(await screen.findByRole("button", { name: "Start from the course structure" }));
    expect(screen.getByRole("textbox", { name: "The big problem" })).toHaveValue("Crypto, step by step.");
    expect(within(preview()).getByText("builds on")).toBeInTheDocument();
    expect(mocks.saveCourseGraph).not.toHaveBeenCalled();
  });

  it("uses a drafted problem and a suggested shape in the architecture, and drops one whose block is gone", async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <Harness
        initial={copy}
        suggestions={[
          { type: "problem", resolves: "no_problem", problem: "Store passwords safely.", draft: true },
          { type: "shape", shape: "layers", section: S2, block: "layer-stack", heading: "The stack" },
          { type: "shape", shape: "tree", section: "33333333-3333-4333-8333-333333333333", block: "tree", heading: "Gone" },
        ]}
      />,
    );

    expect(screen.queryByText(/Gone/)).toBeNull();
    await user.click(screen.getByRole("button", { name: /^Use: Problem, a draft/ }));
    expect(arch()).toMatchObject({ problem: "Store passwords safely.", shape: null });
    expect(screen.queryByText(/Problem, a draft/)).toBeNull();

    await user.click(screen.getByRole("button", { name: /^Use: Layers/ }));
    expect(arch()).toMatchObject({
      problem: "Store passwords safely.",
      shape: "layers",
      nodes: [{ section: S2 }, { section: S2 }],
    });
    expect(screen.queryByRole("button", { name: /^Use:/ })).toBeNull();
  });

  it("dismisses an architecture suggestion for good", async () => {
    const user = userEvent.setup();
    const suggestions = [{ type: "problem" as const, resolves: "no_problem" as const, problem: "Store passwords safely.", draft: true as const }];
    const first = renderWithProviders(<Harness initial={copy} suggestions={suggestions} />);
    await user.click(screen.getByRole("button", { name: /^Dismiss: Problem, a draft/ }));
    expect(screen.queryByText(/Problem, a draft/)).toBeNull();
    first.unmount();
    renderWithProviders(<Harness initial={copy} suggestions={suggestions} />);
    expect(screen.queryByText(/Problem, a draft/)).toBeNull();
  });
});
