import { screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { Checkpoint, EvidenceState, LabAction, LabActionResult, LabDetail } from "../types";
import { LabPlayground } from "./lab-view";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }), usePathname: () => "/labs" }));

const HOSTILE = [
  '<img src="https://example.com/pixel.png" onerror="alert(1)">',
  '<a href="javascript:alert(1)">Verify now</a>',
  "javascript:alert(1)",
  "<script>document.title = 'owned'</script>",
  "From: Support <support@example.test>",
];

const task = (over: Partial<Checkpoint>): Checkpoint => ({
  id: "mark",
  title: "Mark the lines.",
  kind: "findings",
  hint: null,
  passed: false,
  starts_true: false,
  ...over,
});

function lab(over: Partial<LabDetail> = {}, marks: string[] = []): LabDetail {
  return {
    slug: "spot",
    title: "Spot it",
    summary: "S",
    engine: "evidence-review",
    difficulty: 1,
    estimated_minutes: 5,
    domain: { slug: "security-fundamentals", name: "Security Fundamentals" },
    lesson: null,
    progress: { started: true, completed: false, passed: 0, total: 1, holds: 0 },
    briefing: "B",
    scenario: {
      artifacts: [
        {
          id: "email",
          title: "The email",
          max_marks: 2,
          views: [
            { id: "raw", label: "Raw headers", lines: HOSTILE, mono: true },
            { id: "seen", label: "What the recipient saw", lines: ["Dear customer", "Open the link"], mono: false },
          ],
        },
      ],
    },
    checkpoints: [task({})],
    attempt: { id: 3, version: 1, state: { marks }, completed_at: null, restarted: false, debrief: null },
    ...over,
  };
}

/** The lab as a page holds it: every action is answered the way the server would, from the marks. */
function Harness({ initial, onAction }: { initial: LabDetail; onAction?: (a: LabAction) => void }) {
  const [current, setCurrent] = useState(initial);
  const transport = {
    acting: false,
    resetting: false,
    reset: async () => undefined,
    act: async (_version: number, action: LabAction): Promise<LabActionResult> => {
      onAction?.(action);
      const attempt = current.attempt!;
      let marks = (attempt.state as EvidenceState).marks;
      if (action.type === "mark") {
        const key = `${action.artifact}:${action.view}:${action.line}`;
        marks = action.on ? [...marks, key] : marks.filter((k) => k !== key);
      }
      const next = { ...attempt, version: attempt.version + 1, state: { marks } };
      setCurrent({ ...current, attempt: next });
      return {
        output: action.type === "conclude" ? { correct: false } : {},
        newly_passed: [],
        checkpoints: current.checkpoints,
        attempt: next,
      };
    },
  };
  return <LabPlayground lab={current} transport={transport} />;
}

describe("the evidence console", () => {
  it("shows artifacts as inert text: no element, link, image or script comes out of a line", () => {
    const { container } = renderWithProviders(<Harness initial={lab()} />);

    expect(screen.getByText(HOSTILE[0])).toBeInTheDocument();
    expect(screen.getByText(HOSTILE[1])).toBeInTheDocument();
    expect(screen.getByText(HOSTILE[2])).toBeInTheDocument();
    expect(screen.getByText(HOSTILE[3])).toBeInTheDocument();
    const evidence = container.querySelector('[role="listbox"]')!;
    // Nothing in the evidence is an element of its own: no image to load, no link to follow, no script.
    expect(evidence.querySelectorAll("img, a, script, iframe, [onerror], [href], [src]")).toHaveLength(0);
    expect(document.querySelectorAll("img[src*='example.com']")).toHaveLength(0);
    expect(document.title).not.toBe("owned");
  });

  it("marks and unmarks a line from the keyboard, one line at a time, and announces it", async () => {
    const actions: LabAction[] = [];
    const user = userEvent.setup();
    renderWithProviders(<Harness initial={lab()} onAction={(a) => actions.push(a)} />);

    const lines = screen.getAllByRole("option");
    lines[0].focus();
    await user.keyboard("{ArrowDown}{ArrowDown}");
    expect(lines[2]).toHaveFocus();
    await user.keyboard(" ");
    expect(actions).toEqual([{ type: "mark", artifact: "email", view: "raw", line: 3, on: true }]);
    expect(await screen.findByRole("status", { name: "" })).toBeDefined();
    expect(screen.getAllByRole("option")[2]).toHaveAttribute("aria-selected", "true");
    expect(screen.getAllByText(/Line 3 marked\. 1 of at most 2 marked in The email\./).length).toBeGreaterThan(0);

    await user.keyboard("{Enter}");
    expect(actions.at(-1)).toMatchObject({ line: 3, on: false });
    expect(await screen.findAllByText(/Line 3 unmarked/)).not.toHaveLength(0);
    await user.keyboard("{End}{Home}");
    expect(lines[0]).toHaveFocus();
  });

  it("switches between the views with a proper control and keeps each view's lines apart", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Harness initial={lab()} />);

    expect(screen.getByRole("radiogroup", { name: "Views of The email" })).toBeInTheDocument();
    await user.click(screen.getByRole("radio", { name: "What the recipient saw" }));
    expect(screen.getAllByRole("option")).toHaveLength(2);
    expect(screen.getByText("Dear customer")).toBeInTheDocument();
    expect(screen.queryByText(HOSTILE[4])).not.toBeInTheDocument();
  });

  it("tells the learner when an artifact holds more marks than its limit", () => {
    renderWithProviders(<Harness initial={lab({}, ["email:raw:1", "email:raw:2", "email:seen:1"])} />);

    expect(screen.getByText(/Too many lines marked here: at most 2/)).toBeInTheDocument();
  });

  it("counts the marks against the limit before that", () => {
    renderWithProviders(<Harness initial={lab({}, ["email:raw:1"])} />);

    expect(screen.getByText("1 of at most 2 lines marked")).toBeInTheDocument();
  });
});

describe("a conclusion", () => {
  const conclusion = task({
    id: "verdict",
    kind: "conclusion",
    title: "Give your verdict.",
    question: "What is this email?",
    options: [
      { id: "b", text: "Second written, shown first" },
      { id: "a", text: "First written, shown second" },
    ],
    max_cited: 2,
    citable: [{ id: "email:raw:1", label: "The email, Raw headers, line 1: <img src=x>" }],
  });

  it("offers the options in the order it was given and cites only what can be cited", async () => {
    const actions: LabAction[] = [];
    const user = userEvent.setup();
    renderWithProviders(
      <Harness initial={lab({ checkpoints: [conclusion] }, ["email:raw:1"])} onAction={(a) => actions.push(a)} />,
    );

    const radios = screen.getAllByRole("radio").filter((r) => r.getAttribute("name") === "conclusion-verdict");
    expect(radios.map((r) => r.getAttribute("value"))).toEqual(["b", "a"]);
    expect(screen.getByLabelText("The email, Raw headers, line 1: <img src=x>")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Check my conclusion" })).toBeDisabled();

    await user.click(radios[1]);
    await user.click(screen.getByLabelText(/Raw headers, line 1/));
    await user.click(screen.getByRole("button", { name: "Check my conclusion" }));
    expect(actions.at(-1)).toEqual({ type: "conclude", checkpoint: "verdict", option: "a", cited: ["email:raw:1"] });
    expect(await screen.findByText(/Not yet\. Look again/)).toBeInTheDocument();
  });

  it("says to mark evidence first when there is nothing to cite", () => {
    renderWithProviders(<Harness initial={lab({ checkpoints: [{ ...conclusion, citable: [] }] })} />);

    expect(screen.getByText(/Mark some evidence in the console first/)).toBeInTheDocument();
  });
});

describe("the debrief", () => {
  const debrief = {
    indicators: [
      {
        id: "reply",
        title: "Replies go elsewhere",
        explanation: "The Reply-To domain differs.",
        required: true,
        found: true,
        lines: [{ artifact: "The email", view: "Raw headers", line: 5, text: "Reply-To: <x@example.net>" }],
      },
      {
        id: "path",
        title: "Envelope sender differs",
        explanation: "A third domain.",
        required: false,
        found: false,
        lines: [],
      },
    ],
    wrong: [{ artifact: "The email", view: "Raw headers", line: 10, text: "X-Mailer: BulkSend 2.1" }],
    conclusions: [{ checkpoint: "verdict", title: "Give your verdict.", explanation: "Two signs are enough." }],
  };

  it("is shown once the attempt carries one: each indicator, what was missed and what was not evidence", () => {
    const l = lab();
    l.attempt = { ...l.attempt!, completed_at: "2026-11-26T10:00:00Z", debrief };
    renderWithProviders(<Harness initial={l} />);

    const region = screen.getByRole("region", { name: "Debrief" });
    expect(within(region).getByText("Replies go elsewhere")).toBeInTheDocument();
    expect(within(region).getByText("The Reply-To domain differs.")).toBeInTheDocument();
    expect(within(region).getByText("Found")).toBeInTheDocument();
    expect(within(region).getByText(/Missed · bonus/)).toBeInTheDocument();
    expect(within(region).getByText("X-Mailer: BulkSend 2.1")).toBeInTheDocument();
    expect(within(region).getByText("Two signs are enough.")).toBeInTheDocument();
  });

  it("is not on the page while the lab is being played", () => {
    renderWithProviders(<Harness initial={lab()} />);

    expect(screen.queryByRole("region", { name: "Debrief" })).not.toBeInTheDocument();
    expect(screen.queryByText("Replies go elsewhere")).not.toBeInTheDocument();
  });
});
