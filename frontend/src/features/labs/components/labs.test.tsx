import { screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api/client";
import { renderWithProviders } from "@/test/render";
import type { LabActionResult, LabDetail, LabSummary } from "../types";
import { LabView } from "./lab-view";
import { LabsList } from "./labs-list";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }), usePathname: () => "/labs" }));
const mocks = { list: vi.fn(), lab: vi.fn(), start: vi.fn(), act: vi.fn(), reset: vi.fn() };
vi.mock("../api", () => ({
  fetchLabs: () => mocks.list(),
  fetchLab: () => mocks.lab(),
  startLab: () => mocks.start(),
  actInLab: (...args: unknown[]) => mocks.act(...args),
  resetLab: (...args: unknown[]) => mocks.reset(...args),
}));

const summary: LabSummary = {
  slug: "investigate-a-suspicious-login",
  title: "Investigate a suspicious login",
  summary: "Read the logs.",
  engine: "linux-shell",
  difficulty: 1,
  estimated_minutes: 25,
  domain: { slug: "linux", name: "Linux" },
  lesson: { slug: "linux-logs", title: "Finding evidence in logs" },
  progress: { started: false, completed: false, passed: 0, total: 2 },
};

const checkpoints = [
  { id: "read-auth-log", title: "Open the authentication log.", kind: "read", hint: "Try cd /var/log.", passed: false },
  { id: "attacker-ip", title: "Which IP address logged in?", kind: "answer", hint: null, passed: false },
];

const shellLab: LabDetail = {
  ...summary,
  briefing: "Work out **who** got in.",
  scenario: { hostname: "web-01", user: "analyst", commands: ["pwd", "cat"] },
  checkpoints,
  attempt: null,
};

const started: LabDetail = {
  ...shellLab,
  progress: { ...summary.progress, started: true },
  attempt: { id: 5, version: 1, state: { cwd: "/home/analyst", prompt: "analyst@web-01:~$", history: [] }, completed_at: null },
};

function result(over: Partial<LabActionResult> & { history?: { command: string; output: string; error: boolean }[]; version?: number }): LabActionResult {
  return {
    output: over.output ?? {},
    newly_passed: over.newly_passed ?? [],
    checkpoints: over.checkpoints ?? checkpoints,
    attempt: { id: 5, version: over.version ?? 2, state: { cwd: "/var/log", prompt: "analyst@web-01:/var/log$", history: over.history ?? [] }, completed_at: null },
  };
}

describe("labs", () => {
  beforeEach(() => Object.values(mocks).forEach((m) => m.mockReset()));

  it("lists labs with progress and the right action", async () => {
    mocks.list.mockResolvedValue([summary, { ...summary, slug: "b", title: "Lock down", engine: "firewall-rules", progress: { started: true, completed: true, passed: 2, total: 2 } }]);
    renderWithProviders(<LabsList />);

    const first = (await screen.findByRole("link", { name: "Investigate a suspicious login" })).closest("li")!;
    expect(within(first).getByText("0 of 2 tasks done")).toBeInTheDocument();
    expect(within(first).getByRole("link", { name: /Start the lab/ })).toHaveAttribute("href", "/labs/investigate-a-suspicious-login");
    const second = screen.getByRole("link", { name: "Lock down" }).closest("li")!;
    expect(within(second).getByText("Completed")).toBeInTheDocument();
    expect(within(second).getByRole("link", { name: /Open again/ })).toBeInTheDocument();
  });

  it("starts the shell lab, runs commands on the server and checks answers", async () => {
    mocks.lab.mockResolvedValue(shellLab);
    mocks.start.mockResolvedValue(started);
    const user = userEvent.setup();
    renderWithProviders(<LabView slug={summary.slug} />);

    expect(await screen.findByText("who")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: /Start the lab/ }));

    const command = await screen.findByRole("textbox", { name: /Command/ });
    mocks.act.mockResolvedValueOnce(
      result({ output: { text: "Ticket SEC-142", error: false }, newly_passed: ["read-auth-log"], checkpoints: [{ ...checkpoints[0], passed: true }, checkpoints[1]], history: [{ command: "cat notes.txt", output: "Ticket SEC-142", error: false }] }),
    );
    await user.type(command, "cat notes.txt{Enter}");
    expect(mocks.act).toHaveBeenCalledWith(5, 1, { type: "command", command: "cat notes.txt" });
    const log = screen.getByRole("log", { name: "Shell transcript" });
    expect(await within(log).findByText("Ticket SEC-142")).toBeInTheDocument();
    expect(screen.getByText("1 of 2 tasks done")).toBeInTheDocument();

    // Up recalls the last command.
    await user.type(command, "{ArrowUp}");
    expect(command).toHaveValue("cat notes.txt");

    // A wrong answer says so; the server keeps the key.
    mocks.act.mockResolvedValueOnce(result({ output: { checkpoint: "attacker-ip", correct: false }, version: 3, checkpoints: [{ ...checkpoints[0], passed: true }, checkpoints[1]] }));
    await user.type(screen.getByRole("textbox", { name: "Your answer" }), "1.2.3.4");
    await user.click(screen.getByRole("button", { name: "Check" }));
    expect(mocks.act).toHaveBeenLastCalledWith(5, 2, { type: "answer", checkpoint: "attacker-ip", value: "1.2.3.4" });
    expect(await screen.findByText("Not quite. Look again, then try another answer.")).toBeInTheDocument();
  });

  it("reloads after a conflict from another tab and says so", async () => {
    mocks.lab.mockResolvedValue(started);
    mocks.act.mockRejectedValue(new ApiError(409, { message: "stale" }));
    const user = userEvent.setup();
    renderWithProviders(<LabView slug={summary.slug} />);

    await user.type(await screen.findByRole("textbox", { name: /Command/ }), "pwd{Enter}");
    expect(await screen.findByText(/changed in another tab or window/)).toBeInTheDocument();
    expect(mocks.lab).toHaveBeenCalledTimes(2);
  });

  it("drives the firewall through actions and shows the engine's verdict", async () => {
    mocks.lab.mockResolvedValue({
      ...shellLab,
      engine: "firewall-rules",
      scenario: { server: { name: "web-01", ip: "10.0.20.10" }, max_rules: 20 },
      attempt: { id: 6, version: 4, state: { rules: [{ action: "allow", proto: "tcp", port: 22, source: "any" }], default: "allow", tests: [] }, completed_at: null },
    } satisfies LabDetail);
    mocks.act.mockResolvedValue(result({}));
    const user = userEvent.setup();
    renderWithProviders(<LabView slug="lock" />);

    await user.click(await screen.findByRole("button", { name: "Remove rule 1" }));
    expect(mocks.act).toHaveBeenLastCalledWith(6, 4, { type: "remove_rule", index: 0 });

    const add = screen.getByRole("form", { name: "Add a rule" });
    await user.type(within(add).getByRole("textbox", { name: "Port" }), "443");
    await user.click(within(add).getByRole("checkbox", { name: "Add at the top" }));
    await user.click(within(add).getByRole("button", { name: "Add rule" }));
    expect(mocks.act).toHaveBeenLastCalledWith(6, 4, { type: "add_rule", position: 0, rule: { action: "allow", proto: "tcp", port: 443, source: "any" } });

    await user.click(screen.getByRole("radio", { name: "Deny" }));
    expect(mocks.act).toHaveBeenLastCalledWith(6, 4, { type: "set_default", policy: "deny" });
  });

  it("sends HTTP requests with my session cookie and shows the response", async () => {
    mocks.lab.mockResolvedValue({
      ...shellLab,
      engine: "http-inspector",
      scenario: { app: { name: "Mini Shop", origin: "https://shop.example" }, session: { name: "session", value: "abc" }, captured: [], methods: ["GET", "POST"] },
      attempt: { id: 7, version: 1, state: { sent: [] }, completed_at: null },
    } satisfies LabDetail);
    mocks.act.mockResolvedValue(result({ output: { response: { status: 200, headers: { "Content-Type": "application/json" }, body: '{"id":1002}' } } }));
    const user = userEvent.setup();
    renderWithProviders(<LabView slug="shop" />);

    const path = await screen.findByRole("textbox", { name: /Path on https:\/\/shop.example/ });
    await user.clear(path);
    await user.type(path, "/api/orders/1002");
    await user.click(screen.getByRole("button", { name: /Send$/ }));

    expect(mocks.act).toHaveBeenLastCalledWith(7, 1, {
      type: "send",
      request: { method: "GET", path: "/api/orders/1002", headers: { Accept: "application/json" }, cookies: { session: "abc" }, body: "" },
    });
    expect(await screen.findByText(/"id":1002/)).toBeInTheDocument();
  });

  it("says so when a lab does not exist", async () => {
    mocks.lab.mockRejectedValue(new ApiError(404, { message: "Not found" }));
    renderWithProviders(<LabView slug="nope" />);
    expect(await screen.findByText("This lab doesn't exist or is no longer offered.")).toBeInTheDocument();
  });
});
