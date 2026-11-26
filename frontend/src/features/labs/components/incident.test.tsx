import { screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { IncidentState, LabAction, LabActionResult, LabDetail } from "../types";
import { LabPlayground } from "./lab-view";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }), usePathname: () => "/labs" }));

const fresh: IncidentState = {
  situation: "03:40. Files on fs-01 are being renamed.",
  ended: false,
  vars: [
    { id: "network", label: "fs-01 network", value: "connected", value_label: "Connected" },
    { id: "evidence", label: "Volatile evidence", value: "at-risk", value_label: "At risk" },
  ],
  choices: [
    { id: "isolate-port", label: "Disable fs-01's switch port", available: true },
    { id: "capture-live", label: "Capture a memory image", available: true },
    { id: "restore-clean", label: "Restore from the 02:00 backup", available: false },
  ],
  timeline: [],
  dead_end: null,
};

const after: IncidentState = {
  ...fresh,
  situation: "The attacker reaches fs-02.",
  vars: [
    { id: "network", label: "fs-01 network", value: "isolated", value_label: "Isolated" },
    { id: "evidence", label: "Volatile evidence", value: "at-risk", value_label: "At risk" },
  ],
  choices: fresh.choices.map((c) => (c.id === "isolate-port" ? { ...c, available: false } : c)),
  timeline: [
    {
      decision: "Disable fs-01's switch port",
      consequence: "The outbound connection drops.",
      attacker: "The attacker reaches fs-02.",
      changes: [{ label: "fs-01 network", from: "Connected", to: "Isolated" }],
    },
  ],
};

function lab(state: IncidentState, over: Partial<LabDetail> = {}): LabDetail {
  return {
    slug: "ir",
    title: "Contain it",
    summary: "S",
    engine: "incident-response",
    difficulty: 2,
    estimated_minutes: 20,
    domain: { slug: "security-fundamentals", name: "Security Fundamentals" },
    lesson: null,
    progress: { started: true, completed: false, passed: 0, total: 2, holds: 0 },
    briefing: "B",
    scenario: { variables: [] },
    checkpoints: [
      {
        id: "evidence-kept",
        title: "Evidence is preserved.",
        kind: "outcome",
        hint: null,
        passed: false,
        starts_true: false,
      },
      { id: "restored", title: "Shares are restored.", kind: "outcome", hint: null, passed: false, starts_true: false },
    ],
    attempt: { id: 4, version: 1, state, completed_at: null, restarted: false, debrief: null },
    ...over,
  };
}

function Harness({
  initial,
  next,
  onAction,
}: {
  initial: LabDetail;
  next?: IncidentState;
  onAction?: (a: LabAction) => void;
}) {
  const [current, setCurrent] = useState(initial);
  const transport = {
    acting: false,
    resetting: false,
    reset: async () => undefined,
    act: async (_v: number, action: LabAction): Promise<LabActionResult> => {
      onAction?.(action);
      const attempt = { ...current.attempt!, version: 2, state: next ?? current.attempt!.state };
      setCurrent({ ...current, attempt });
      return {
        output: {
          consequence: "The outbound connection drops.",
          attacker: "The attacker reaches fs-02.",
          changes: [{ label: "fs-01 network", from: "Connected", to: "Isolated" }],
        },
        newly_passed: [],
        checkpoints: current.checkpoints,
        attempt,
      };
    },
  };
  return <LabPlayground lab={current} transport={transport} />;
}

describe("the incident console", () => {
  it("shows the state, an empty timeline and the actions as buttons, with the ones that are not open disabled", () => {
    renderWithProviders(<Harness initial={lab(fresh)} />);

    const state = screen.getByRole("region", { name: "Incident state" });
    expect(within(state).getByText("Connected")).toBeInTheDocument();
    expect(screen.getByText(/No decisions yet/)).toBeInTheDocument();
    expect(screen.getByText(fresh.situation)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Capture a memory image" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Restore from the 02:00 backup" })).toBeDisabled();
    expect(screen.getByText("Not available in the current state")).toBeInTheDocument();
  });

  it("sends the choice, then shows the timeline as an ordered list with what changed and the attacker's move, and announces it", async () => {
    const actions: LabAction[] = [];
    const user = userEvent.setup();
    renderWithProviders(<Harness initial={lab(fresh)} next={after} onAction={(a) => actions.push(a)} />);

    await user.click(screen.getByRole("button", { name: "Disable fs-01's switch port" }));
    expect(actions).toEqual([{ type: "choose", choice: "isolate-port" }]);

    const timeline = screen.getByRole("region", { name: "Timeline" });
    const entries = within(timeline).getAllByRole("listitem");
    expect(within(timeline).getAllByRole("list")[0].tagName).toBe("OL");
    expect(entries).toHaveLength(1 + 1); // the entry and its list of changes
    expect(within(timeline).getByText("The outbound connection drops.")).toBeInTheDocument();
    expect(within(timeline).getByText(/The attacker reaches fs-02\./)).toBeInTheDocument();
    expect(within(screen.getByRole("region", { name: "Incident state" })).getByText("Isolated")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Disable fs-01's switch port" })).toBeDisabled();
    const live = screen.getAllByRole("status").find((el) => el.className.includes("sr-only"))!;
    expect(live).toHaveTextContent(/What changed: fs-01 network: Connected → Isolated/);
    expect(live).toHaveTextContent(/The attacker's next move: The attacker reaches fs-02\./);
  });

  it("says plainly that a task is out of reach, and points to starting over, without naming a choice", () => {
    const stuck: IncidentState = { ...after, dead_end: { unreachable: ["evidence-kept"] } };
    renderWithProviders(<Harness initial={lab(stuck)} />);

    const alert = screen.getByText(
      /This path can no longer complete every task\. Out of reach: Evidence is preserved\./,
    );
    expect(alert).toBeInTheDocument();
    expect(screen.getByText("Start over to try a different approach.")).toBeInTheDocument();
    expect(alert.closest('[role="alert"]')!.textContent).not.toMatch(/capture|power|isolate/i);
  });

  it("says so when the tasks cannot be completed together", () => {
    renderWithProviders(<Harness initial={lab({ ...after, dead_end: { unreachable: [] } })} />);

    expect(screen.getByText("This path can no longer complete every task together.")).toBeInTheDocument();
  });

  it("shows no actions once the incident has ended", () => {
    renderWithProviders(
      <Harness initial={lab({ ...after, ended: true, choices: [], situation: "Recovery is complete." })} />,
    );

    expect(screen.getByRole("heading", { name: "The incident has ended" })).toBeInTheDocument();
    expect(screen.queryByRole("list", { name: "Actions you can take" })).not.toBeInTheDocument();
  });
});

describe("the incident debrief", () => {
  it("lists the path with each trade-off, and for each other choice whether every task stayed possible", () => {
    const l = lab({ ...after, ended: true, choices: [] });
    l.attempt = {
      ...l.attempt!,
      completed_at: "2026-11-27T10:00:00Z",
      debrief: {
        path: [
          {
            decision: "Disable fs-01's switch port",
            tradeoff: "Stops the transfer and keeps memory.",
            alternatives: [
              { label: "Power fs-01 off at once", tradeoff: "Destroys the memory image.", keeps_open: false },
              { label: "Stop the encrypting process", tradeoff: "Stops damage quickly.", keeps_open: true },
            ],
          },
        ],
        final: [{ label: "File shares", value: "Restored from backup" }],
        conclusions: [{ checkpoint: "verdict", title: "Verdict", explanation: "A credential, not a flaw.", tries: 1 }],
      },
    };
    renderWithProviders(<Harness initial={l} />);

    const region = screen.getByRole("region", { name: "Debrief" });
    expect(within(region).getByText("Stops the transfer and keeps memory.")).toBeInTheDocument();
    expect(within(region).getByText("a task becomes impossible")).toBeInTheDocument();
    expect(within(region).getByText("every task stays possible")).toBeInTheDocument();
    expect(within(region).getByText("Restored from backup")).toBeInTheDocument();
    expect(within(region).getByText("Reached in 1 try.")).toBeInTheDocument();
  });
});
