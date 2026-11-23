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
  course: { id: 1, slug: "crypto", title: "Crypto", is_published: true },
  architecture_issues: [],
} as unknown as AdminLesson;

function Harness({ initial, errors = {} }: { initial: WorkingCopy; errors?: Record<string, string[]> }) {
  const [state, setState] = useState(initial);
  return (
    <>
      <ArchitecturePanel
        copy={state}
        lesson={lesson}
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
      slug: "hashing",
      title: "Hashing",
      status: "published",
      problem: "Store passwords.",
      shape: null,
      architecture: null,
    },
    { slug: "tls", title: "TLS", status: "published", problem: null, shape: null, architecture: null },
  ],
  issues: [{ type: "no_problem", lesson: "tls" }],
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
