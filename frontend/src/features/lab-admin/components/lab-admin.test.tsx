import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, resolve } from "node:path";
import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import type { LabDetail } from "@/features/labs/types";
import { ApiError } from "@/lib/api/client";
import { renderWithProviders } from "@/test/render";
import type { AdminLab, LabsIndex } from "../types";
import { LabEditor } from "./lab-editor";
import { LabsAdminView } from "./labs-admin-view";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => "/admin/labs",
}));

const mocks = vi.hoisted(() => ({
  fetchAdminLabs: vi.fn(),
  fetchAdminLab: vi.fn(),
  saveLabDraft: vi.fn(),
  publishLab: vi.fn(),
  checkLab: vi.fn(),
  openPreview: vi.fn(),
  actInPreview: vi.fn(),
  resetPreview: vi.fn(),
  archiveLab: vi.fn(),
  orderLabs: vi.fn(),
  createLab: vi.fn(),
  discardLabDraft: vi.fn(),
  fetchLabRevisions: vi.fn(),
  restoreLabRevision: vi.fn(),
}));
vi.mock("../api", () => mocks);

const SECRET = "SECRET-ANSWER-9f3a";

const lab: AdminLab = {
  id: 7,
  slug: "my-lab",
  status: "published",
  is_archived: false,
  position: 1,
  has_draft: false,
  owned: true,
  lock_version: 0,
  scenario_version: 1,
  impact: { breaking: false, in_progress: 0, completed: 0 },
  domains: [{ slug: "linux", name: "Linux" }],
  definition: {
    title: "My lab",
    summary: "A summary.",
    briefing: "A briefing.",
    domain: "linux",
    lesson: null,
    engine: "linux-shell",
    difficulty: 1,
    estimated_minutes: 10,
    scenario: {
      hostname: "lab-01",
      user: "learner",
      home: "/home/learner",
      cwd: "/home/learner",
      files: { "/home/learner/notes.txt": "hello\n" },
      checkpoints: [{ id: "name", kind: "answer", title: "What is the name?", answers: [SECRET] }],
    },
    solutions: {
      solutions: [{ name: "Answer it", actions: [{ type: "answer", checkpoint: "name", value: SECRET }] }],
      decoys: [],
    },
  },
};

const index: LabsIndex = {
  data: [
    {
      id: 1,
      slug: "from-file",
      title: "From a file",
      engine: "linux-shell",
      status: "published",
      is_archived: false,
      difficulty: 1,
      domain: { slug: "linux", name: "Linux" },
      has_draft: false,
      owned: false,
      shadowed: true,
      started: 3,
      completed: 1,
    },
    {
      id: 2,
      slug: "from-admin",
      title: "From the admin",
      engine: "firewall-rules",
      status: "published",
      is_archived: false,
      difficulty: 2,
      domain: { slug: "linux", name: "Linux" },
      has_draft: true,
      owned: true,
      shadowed: false,
      started: 0,
      completed: 0,
    },
  ],
  meta: {
    domains: [{ slug: "linux", name: "Linux" }],
    engines: [{ key: "linux-shell", checkpoint_kinds: ["answer", "read"] }],
  },
};

const previewLab: LabDetail = {
  slug: "my-lab",
  title: "My lab",
  summary: "A summary.",
  engine: "linux-shell",
  difficulty: 1,
  estimated_minutes: 10,
  domain: { slug: "linux", name: "Linux" },
  lesson: null,
  progress: { started: true, completed: false, passed: 0, total: 1, holds: 0 },
  briefing: "A briefing.",
  scenario: { hostname: "lab-01", user: "learner", commands: ["cat"] },
  checkpoints: [
    { id: "name", title: "What is the name?", kind: "answer", hint: null, passed: false, starts_true: false },
  ],
  attempt: {
    id: 0,
    version: 1,
    state: { cwd: "/home/learner", prompt: "learner@lab-01:~$", history: [] },
    completed_at: null,
    restarted: false, debrief: null,
  },
};

beforeAll(() => {
  // jsdom has no <dialog> behaviour; open and close it the way the browser would.
  HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) {
    this.setAttribute("open", "");
  };
  HTMLDialogElement.prototype.close ??= function (this: HTMLDialogElement) {
    this.removeAttribute("open");
    this.dispatchEvent(new Event("close"));
  };
});

beforeEach(() => {
  Object.values(mocks).forEach((m) => m.mockReset());
  mocks.fetchAdminLab.mockResolvedValue(lab);
  mocks.fetchAdminLabs.mockResolvedValue(index);
});

describe("the labs list", () => {
  it("shows who owns each lab and says what to do about a shadowed entry", async () => {
    renderWithProviders(<LabsAdminView />);

    const file = (await screen.findByRole("link", { name: "From a file" })).closest("tr")!;
    expect(within(file).getByText("Content file")).toBeInTheDocument();
    expect(
      within(file).getByText(/Delete the "from-file" entry from database\/seeders\/content\/labs\.php/),
    ).toBeInTheDocument();
    const admin = screen.getByRole("link", { name: "From the admin" }).closest("tr")!;
    expect(within(admin).getByText("Database")).toBeInTheDocument();
    expect(within(admin).getByText("Unpublished changes")).toBeInTheDocument();
    expect(within(file).getByText("3 started, 1 finished")).toBeInTheDocument();
  });

  it("orders labs with buttons, sending every id", async () => {
    mocks.orderLabs.mockResolvedValue({ ids: [2, 1] });
    renderWithProviders(<LabsAdminView />);
    await userEvent.click(await screen.findByRole("button", { name: "Move From a file down" }));

    await waitFor(() => expect(mocks.orderLabs).toHaveBeenCalledWith([2, 1]));
  });
});

describe("publishing a change that restarts learners", () => {
  it("asks first with the count from the server, then sends confirm_restart", async () => {
    mocks.publishLab
      .mockRejectedValueOnce(
        new ApiError(422, { message: "x", errors: { confirm_restart: ["This change restarts the lab."] } }),
      )
      .mockResolvedValueOnce({ ...lab, has_draft: false });
    mocks.fetchAdminLab.mockResolvedValue({
      ...lab,
      has_draft: true,
      lock_version: 1,
      impact: { breaking: true, in_progress: 3, completed: 2 },
    });
    renderWithProviders(<LabEditor id={7} />);

    await userEvent.click(await screen.findByRole("button", { name: "Publish" }));
    const first = await screen.findByRole("dialog", { name: "Publish this lab?" });
    await userEvent.click(within(first).getByRole("button", { name: "Publish" }));

    const confirm = await screen.findByRole("dialog", { name: "This change restarts the lab for learners" });
    expect(
      within(confirm).getByText(/3 learners are part-way through this lab and 2 have finished it before/),
    ).toBeInTheDocument();
    expect(mocks.publishLab).toHaveBeenCalledWith(7, expect.not.objectContaining({ confirm_restart: true }));

    await userEvent.click(within(confirm).getByRole("button", { name: "Publish and restart" }));
    await waitFor(() =>
      expect(mocks.publishLab).toHaveBeenLastCalledWith(7, expect.objectContaining({ confirm_restart: true })),
    );
    expect(await screen.findByText("Published. Learners see this version now.")).toBeInTheDocument();
  });
});

describe("the preview", () => {
  it("says in words that it ran out, and restarts on request", async () => {
    mocks.openPreview.mockResolvedValue(previewLab);
    mocks.actInPreview.mockRejectedValue(
      new ApiError(409, { message: "This preview changed in another tab, or the draft changed. Reload to continue." }),
    );
    mocks.resetPreview.mockResolvedValue(previewLab);
    renderWithProviders(<LabEditor id={7} />);

    await userEvent.click(await screen.findByRole("tab", { name: "Preview" }));
    const command = await screen.findByLabelText(/command/i, {}, { timeout: 4000 });
    await userEvent.type(command, "pwd{Enter}");

    const alert = await screen.findByText(/This preview ran out of time, or the draft changed under it/);
    expect(alert).toBeInTheDocument();
    expect(screen.queryByText(/Reload to continue/)).not.toBeInTheDocument();

    const restart = within(alert.closest('[role="status"]') as HTMLElement).getByRole("button", {
      name: "Restart preview",
    });
    await userEvent.click(restart);
    await waitFor(() => expect(mocks.resetPreview).toHaveBeenCalled());
    await waitFor(() => expect(screen.queryByText(/ran out of time/)).not.toBeInTheDocument());
  });
});

describe("answers stay out of the console and the error text", () => {
  it("logs nothing when a save fails, and shows paths without values", async () => {
    const spies = (["error", "warn", "log", "info", "debug"] as const).map((m) =>
      vi.spyOn(console, m).mockImplementation(() => undefined),
    );
    mocks.saveLabDraft.mockRejectedValue(
      new ApiError(422, {
        message: "The title is required.",
        errors: { "scenario.checkpoints.0.answers": ["Add at least 1 items."] },
      }),
    );
    renderWithProviders(<LabEditor id={7} />);

    await userEvent.clear(await screen.findByLabelText("Title"));
    await userEvent.type(screen.getByLabelText("Title"), "Changed");
    await userEvent.click(screen.getByRole("button", { name: "Save draft" }));

    expect((await screen.findAllByText("scenario.checkpoints.0.answers")).length).toBeGreaterThan(0);
    // The admin sees the answers in the boxes they edit; the messages about a failure never repeat them.
    for (const alert of screen.getAllByRole("alert")) expect(alert.textContent).not.toContain(SECRET);
    for (const spy of spies) expect(spy).not.toHaveBeenCalled();
    spies.forEach((s) => s.mockRestore());
  });

  it("never mentions the console in the feature's source", () => {
    const files: string[] = [];
    const walk = (dir: string) =>
      readdirSync(dir).forEach((f) => {
        const p = join(dir, f);
        if (statSync(p).isDirectory()) walk(p);
        else if (/\.(ts|tsx)$/.test(f) && !/\.test\./.test(f)) files.push(p);
      });
    walk(resolve(__dirname, ".."));
    expect(files.length).toBeGreaterThan(8);
    for (const file of files) expect(readFileSync(file, "utf8"), file).not.toMatch(/\bconsole\./);
  });
});

describe("running the solutions", () => {
  it("marks each entry from the server's report", async () => {
    mocks.checkLab.mockResolvedValue({
      valid: false,
      errors: { "solutions.solutions.0": ["This solution does not complete the lab."] },
      warnings: {},
    });
    renderWithProviders(<LabEditor id={7} />);

    await userEvent.click(await screen.findByRole("tab", { name: "Solutions" }));
    await userEvent.click(screen.getByRole("button", { name: "Run solutions" }));

    expect(await screen.findByText("Something does not work. See the entries below.")).toBeInTheDocument();
    expect(screen.getByText(/Does not work/)).toBeInTheDocument();
    expect(screen.getAllByText(/This solution does not complete the lab/).length).toBeGreaterThan(0);
  });
});
