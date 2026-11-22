import { screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { User } from "@/features/auth/types";
import { renderWithProviders } from "@/test/render";
import type { LearningPath } from "../types";
import { RoadmapView } from "./roadmap-view";

const push = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push, replace: vi.fn(), refresh: vi.fn() }), usePathname: () => "/roadmap" }));

const mocks = { fetchLearningPath: vi.fn(), generateLearningPath: vi.fn(), fetchPathVersions: vi.fn(), me: vi.fn(), active: vi.fn() };
vi.mock("../api", () => ({
  fetchLearningPath: () => mocks.fetchLearningPath(),
  generateLearningPath: () => mocks.generateLearningPath(),
  fetchPathVersions: () => mocks.fetchPathVersions(),
}));
vi.mock("@/features/auth/api", () => ({ fetchCurrentUser: () => mocks.me() }));
vi.mock("@/features/assessment/api", () => ({ fetchActiveAssessment: () => mocks.active() }));

const user = (career: { slug: string; title: string } | null): User => ({
  id: 1,
  name: "Lan",
  email: "lan@example.com",
  role: "learner",
  email_verified_at: null,
  created_at: null,
  profile: { avatar_url: null, phone: null, date_of_birth: null, education_level: null, locale: "en", theme: "dark", timezone: null, compass_enabled: true, shortcuts: {} },
  learning_goal: career ? { career, self_level: null, weekly_minutes: 120, domains: [] } : null,
});

const lesson = (slug: string, status: "not_started" | "started" | "completed") => ({ slug, title: `Lesson ${slug}`, summary: `About ${slug}.`, estimated_minutes: 20, is_required: true, status, exercise: null });

const path: LearningPath = {
  id: 3,
  version: 2,
  status: "active",
  generated_at: "2026-11-21T00:00:00Z",
  career: { slug: "soc-analyst", title: "SOC Analyst" },
  assessment: { attempt_id: 9, score: 53, submitted_at: "2026-11-20T00:00:00Z" },
  completion_percent: 25,
  estimate: { remaining_minutes: 60, weekly_minutes: 120, weeks: 1 },
  next_lesson: { slug: "b", title: "Lesson b", topic: "Linux shell essentials" },
  outdated: ["new_assessment"],
  nodes: [
    {
      topic: { slug: "networking-basics", title: "Networking basics", summary: "", level: 1, domain: { slug: "networking", name: "Networking" } },
      stage: 1, position: 1, recommendation: "review", reason: { key: "strength", params: { domain: "Networking", score: 100 } },
      status: "available", percent: 0, requires: [], lessons: [lesson("a", "not_started")],
    },
    {
      topic: { slug: "linux-shell", title: "Linux shell essentials", summary: "", level: 1, domain: { slug: "linux", name: "Linux" } },
      stage: 1, position: 2, recommendation: "required", reason: { key: "gap", params: { domain: "Linux", score: 22 } },
      status: "in_progress", percent: 50, requires: [], lessons: [lesson("c", "completed"), lesson("b", "not_started")],
    },
    {
      topic: { slug: "linux-investigation", title: "Investigating a Linux system", summary: "", level: 2, domain: { slug: "linux", name: "Linux" } },
      stage: 2, position: 1, recommendation: "required", reason: { key: "gap", params: { domain: "Linux", score: 22 } },
      status: "locked", percent: 0, requires: ["linux-shell"], lessons: [lesson("d", "not_started")],
    },
  ],
  edges: [{ from: "linux-shell", to: "linux-investigation" }],
};

describe("RoadmapView", () => {
  beforeEach(() => {
    Object.values(mocks).forEach((m) => m.mockReset());
    mocks.fetchPathVersions.mockResolvedValue([]);
    mocks.active.mockResolvedValue({ latest_result: null });
  });

  it("shows stages, statuses, reasons, prerequisites and the next lesson", async () => {
    mocks.me.mockResolvedValue(user({ slug: "soc-analyst", title: "SOC Analyst" }));
    mocks.fetchLearningPath.mockResolvedValue(path);
    renderWithProviders(<RoadmapView />);

    expect(await screen.findByRole("heading", { name: "Stage 1" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Stage 2" })).toBeInTheDocument();
    expect(screen.getByText("You scored 100% in Networking. Review it if you like, or skip ahead.")).toBeInTheDocument();
    expect(screen.getByText("Review")).toBeInTheDocument();
    expect(screen.getByText("Locked")).toBeInTheDocument();
    expect(screen.getByText("Needs: Linux shell essentials")).toBeInTheDocument();
    expect(screen.getByText("About 1 week at 2 h a week")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Continue/ })).toHaveAttribute("href", "/learn/b");

    // The topic holding the next lesson starts open; the others start closed.
    const toggles = screen.getAllByRole("button", { name: /lesson/ });
    expect(toggles.map((b) => b.getAttribute("aria-expanded"))).toEqual(["false", "true", "false"]);
    await userEvent.click(toggles[2]);
    expect(within(document.getElementById(toggles[2].getAttribute("aria-controls")!)!).getByRole("link", { name: "Lesson d" })).toHaveAttribute("href", "/learn/d");
  });

  it("explains why it is out of date and updates only on request", async () => {
    mocks.me.mockResolvedValue(user({ slug: "soc-analyst", title: "SOC Analyst" }));
    mocks.fetchLearningPath.mockResolvedValue(path);
    mocks.generateLearningPath.mockResolvedValue({ ...path, version: 3, outdated: [] });
    renderWithProviders(<RoadmapView />);

    expect(await screen.findByText("You've submitted a newer assessment since it was built.")).toBeInTheDocument();
    expect(mocks.generateLearningPath).not.toHaveBeenCalled();
    await userEvent.click(screen.getByRole("button", { name: /Update my roadmap/ }));
    expect(await screen.findByText("Version 3")).toBeInTheDocument();
    expect(screen.queryByText("This roadmap is out of date")).toBeNull();
  });

  it("asks for a career goal when there is none", async () => {
    mocks.me.mockResolvedValue(user(null));
    mocks.fetchLearningPath.mockResolvedValue(null);
    renderWithProviders(<RoadmapView />);

    expect(await screen.findByRole("link", { name: /Choose a career goal/ })).toHaveAttribute("href", "/careers");
    expect(screen.queryByRole("button", { name: /Build my roadmap/ })).toBeNull();
  });

  it("offers to build the roadmap once a goal is set, suggesting the assessment first", async () => {
    mocks.me.mockResolvedValue(user({ slug: "soc-analyst", title: "SOC Analyst" }));
    mocks.fetchLearningPath.mockResolvedValue(null);
    mocks.generateLearningPath.mockResolvedValue(path);
    renderWithProviders(<RoadmapView />);

    expect(await screen.findByText("Your goal is SOC Analyst.")).toBeInTheDocument();
    expect(await screen.findByText(/You haven't taken the assessment yet/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole("button", { name: /Build my roadmap/ }));
    expect(mocks.generateLearningPath).toHaveBeenCalledTimes(1);
    expect(push).toHaveBeenCalledWith("/roadmap");
  });

  describe("on wide screens", () => {
    beforeEach(() => {
      vi.stubGlobal("matchMedia", (query: string) => ({ matches: query.includes("min-width"), addEventListener: vi.fn(), removeEventListener: vi.fn() }));
      HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement) {
        this.setAttribute("open", "");
      };
      HTMLDialogElement.prototype.close = function (this: HTMLDialogElement) {
        this.removeAttribute("open");
        this.dispatchEvent(new Event("close"));
      };
    });
    afterEach(() => vi.unstubAllGlobals());

    it("draws the graph: topic nodes that expand into lessons, and a lesson drawer", async () => {
      mocks.me.mockResolvedValue(user({ slug: "soc-analyst", title: "SOC Analyst" }));
      mocks.fetchLearningPath.mockResolvedValue(path);
      renderWithProviders(<RoadmapView />);

      const graph = await screen.findByRole("group", { name: "SOC Analyst roadmap graph" });
      expect(screen.queryByRole("heading", { name: "Stage 1" })).toBeNull();
      expect(within(graph).getByText("Stage 2")).toBeInTheDocument();

      // Topic nodes are buttons, described by their status, reason and prerequisites.
      const nodes = within(graph).getAllByRole("button", { expanded: false }).concat(within(graph).getAllByRole("button", { expanded: true }));
      expect(nodes).toHaveLength(3);
      const locked = within(graph).getByRole("button", { name: /Investigating a Linux system/ });
      expect(locked).toHaveAccessibleDescription(/Locked .*Needs: Linux shell essentials/);

      // The topic with the next lesson starts open.
      const shell = within(graph).getByRole("button", { name: /Linux shell essentials/ });
      expect(shell).toHaveAttribute("aria-expanded", "true");
      await userEvent.click(locked);
      expect(locked).toHaveAttribute("aria-expanded", "true");
      const lessons = within(graph).getByRole("list", { name: "Lessons in Investigating a Linux system" });

      await userEvent.click(within(lessons).getByRole("button", { name: /Lesson d/ }));
      const drawer = await screen.findByRole("dialog", { name: "Lesson d" });
      expect(within(drawer).getByText("About d.")).toBeInTheDocument();
      expect(within(drawer).getByRole("link", { name: /Start lesson/ })).toHaveAttribute("href", "/learn/d");

      await userEvent.click(screen.getByRole("button", { name: "Collapse all" }));
      expect(shell).toHaveAttribute("aria-expanded", "false");
    });
  });
});

