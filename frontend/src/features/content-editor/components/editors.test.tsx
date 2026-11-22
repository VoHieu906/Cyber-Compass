import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { AdminLesson, AdminPost } from "../types";
import { LessonEditor } from "./lesson-editor";
import { PostEditor } from "./post-editor";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => "/admin",
}));

const mocks = vi.hoisted(() => ({
  lesson: { fetch: vi.fn(), saveDraft: vi.fn(), fetchRevisions: vi.fn() },
  post: { fetch: vi.fn(), saveDraft: vi.fn(), fetchRevisions: vi.fn() },
}));
vi.mock("../api", () => {
  const api = (m: typeof mocks.post) => ({
    fetch: () => m.fetch(),
    saveDraft: (...args: unknown[]) => m.saveDraft(...args),
    fetchRevisions: () => m.fetchRevisions(),
    discardDraft: vi.fn(),
    publish: vi.fn(),
    archive: vi.fn(),
    unarchive: vi.fn(),
    remove: vi.fn(),
    restoreRevision: vi.fn(),
  });
  return {
    lessonApi: api(mocks.lesson),
    postApi: api(mocks.post),
    fetchBlockTypes: async () => [
      { key: "prose", label: "Text", description: "Paragraphs", editor: "form", schema: {}, example: { text: "" } },
    ],
    fetchCourses: async () => [],
    fetchMedia: async () => ({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } }),
  };
});

const shared = {
  id: 7,
  published_at: null,
  slug_editable: true,
  has_unpublished_changes: true,
  lock_version: 1,
  draft: { updated_at: null, updated_by: "Ada" },
  can_delete: true,
  preview_sections: [],
};
const post: AdminPost = {
  ...shared,
  slug: "containers",
  status: "draft",
  cover: null,
  working_copy: {
    slug: "containers",
    title: "Containers",
    summary: "",
    estimated_minutes: 3,
    cover: null,
    tags: ["docker"],
    sections: [
      { id: "a1b2c3d4-0000-4000-8000-000000000001", type: "prose", heading: "Why", payload: { text: "Because." } },
    ],
  },
};
const lesson: AdminLesson = {
  ...shared,
  slug: "osi-model",
  status: "published",
  course: { id: 1, slug: "net", title: "Networking", is_published: true },
  chapter: { id: 2, title: "Models" },
  topics: [],
  exercise_references: {},
  working_copy: {
    chapter_id: 2,
    slug: "osi-model",
    title: "The OSI model",
    summary: "Seven layers.",
    estimated_minutes: 15,
    objectives: ["List the layers"],
    key_takeaways: [],
    resources: [],
    sections: [],
  },
};

describe("block-document editor adapters", () => {
  beforeEach(() => {
    mocks.post.fetch.mockResolvedValue(post);
    mocks.lesson.fetch.mockResolvedValue(lesson);
  });

  it("edits a post with its own fields and none of the lesson-only ones", async () => {
    renderWithProviders(<PostEditor id={7} />);

    expect(await screen.findByRole("heading", { level: 2, name: "Post details" })).toBeInTheDocument();
    expect(screen.getByText("Cover image")).toBeInTheDocument();
    expect(screen.getByText("Tags")).toBeInTheDocument();
    expect(screen.getByText("3 minutes")).toBeInTheDocument();
    expect(screen.queryByLabelText("Chapter")).not.toBeInTheDocument();
    expect(screen.queryByText("Learning objectives")).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/Reading time \(minutes\)/)).not.toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Blog posts" })).toHaveAttribute("href", "/admin/posts");
  });

  it("saves the post's working copy through the post API with its lock version", async () => {
    const user = userEvent.setup();
    mocks.post.saveDraft.mockImplementation(async (_id: number, copy: AdminPost["working_copy"]) => ({
      ...post,
      lock_version: 2,
      working_copy: copy,
    }));
    renderWithProviders(<PostEditor id={7} />);

    const title = await screen.findByLabelText("Title");
    await user.clear(title);
    await user.type(title, "Containers, explained");
    await user.click(screen.getByRole("button", { name: "Save draft" }));

    await waitFor(() => expect(mocks.post.saveDraft).toHaveBeenCalled());
    const [id, copy, lockVersion] = mocks.post.saveDraft.mock.calls[0];
    expect(id).toBe(7);
    expect(lockVersion).toBe(1);
    expect(copy).toMatchObject({ title: "Containers, explained", tags: ["docker"], cover: null });
    expect(mocks.lesson.saveDraft).not.toHaveBeenCalled();
  });

  it("keeps the lesson editor as it was: chapter, objectives and typed reading time", async () => {
    const consoleError = vi.spyOn(console, "error");
    renderWithProviders(<LessonEditor id={7} />);

    expect(await screen.findByRole("heading", { level: 2, name: "Lesson details" })).toBeInTheDocument();
    expect(screen.getByLabelText("Chapter")).toBeInTheDocument();
    expect(screen.getByText("Learning objectives")).toBeInTheDocument();
    expect(screen.getByLabelText("Reading time (minutes)")).toHaveValue(15);
    expect(screen.getByText("Networking · Models")).toBeInTheDocument();
    expect(screen.queryByText("Cover image")).not.toBeInTheDocument();
    // Every message the lesson editor asks for exists (post-only wording must not leak into it).
    expect(consoleError.mock.calls.flat().join(" ")).not.toMatch(/MISSING_MESSAGE/);
    consoleError.mockRestore();
  });
});
