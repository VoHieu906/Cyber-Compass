import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { Attempt, AttemptResult } from "../types";
import { AttemptRunner } from "./attempt-runner";
import { ResultView } from "./result-view";

const replace = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace, push: vi.fn(), refresh: vi.fn() }),
  usePathname: () => "/assessment",
}));

const api = {
  fetchAttempt: vi.fn(),
  saveAnswer: vi.fn(),
  submitAttempt: vi.fn(),
  fetchResult: vi.fn(),
  startAttempt: vi.fn(),
};
vi.mock("../api", () => ({
  fetchAttempt: (...a: unknown[]) => api.fetchAttempt(...a),
  saveAnswer: (...a: unknown[]) => api.saveAnswer(...a),
  submitAttempt: (...a: unknown[]) => api.submitAttempt(...a),
  fetchResult: (...a: unknown[]) => api.fetchResult(...a),
  startAttempt: (...a: unknown[]) => api.startAttempt(...a),
}));

const formula = { weights: { "1": 1, "2": 2, "3": 3 }, unanswered: "incorrect", developing_from: 40, strength_from: 75 } as const;

function attempt(): Attempt {
  const q = (id: number, domain: string) => ({
    id,
    position: id,
    domain: { slug: domain, name: domain === "net" ? "Networking" : "Linux" },
    difficulty: 1 as const,
    prompt: `Question text ${id}`,
    code: null,
    options: [
      { id: id * 10, label: `Option A${id}` },
      { id: id * 10 + 1, label: `Option B${id}` },
    ],
    selected_option_id: null,
  });
  return {
    id: 7,
    status: "in_progress",
    assessment: { slug: "initial-knowledge", title: "Assessment" },
    started_at: "2026-11-21T00:00:00Z",
    submitted_at: null,
    total: 2,
    answered: 0,
    questions: [q(1, "net"), q(2, "linux")],
  };
}

describe("AttemptRunner", () => {
  beforeEach(() => {
    Object.values(api).forEach((m) => m.mockReset());
    replace.mockReset();
  });

  it("saves quick changes in order so the last choice wins, then submits", async () => {
    api.fetchAttempt.mockResolvedValue(attempt());
    const resolvers: (() => void)[] = [];
    api.saveAnswer.mockImplementation(
      (_id: number, questionId: number, optionId: number | null) =>
        new Promise((resolve) => resolvers.push(() => resolve({ question_id: questionId, selected_option_id: optionId, answered: 1 }))),
    );
    renderWithProviders(<AttemptRunner id={7} />);

    await userEvent.click(await screen.findByLabelText("Option A1"));
    await userEvent.click(screen.getByLabelText("Option B1"));
    expect(screen.getByText("Saving")).toBeInTheDocument();
    // The second save waits for the first, and sends the latest choice.
    expect(api.saveAnswer).toHaveBeenCalledTimes(1);
    resolvers.shift()!();
    await waitFor(() => expect(api.saveAnswer).toHaveBeenCalledTimes(2));
    expect(api.saveAnswer).toHaveBeenLastCalledWith(7, 1, 11);
    resolvers.shift()!();
    expect(await screen.findByText("Answers saved")).toBeInTheDocument();
    expect(screen.getByText("1 of 2 answered")).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: /Next/ }));
    // The rail and the footer both offer it; use the footer one.
    await userEvent.click(screen.getAllByRole("button", { name: /Review and submit/ }).at(-1)!);
    expect(screen.getByText(/1 question is unanswered and will count as wrong/)).toBeInTheDocument();

    api.submitAttempt.mockResolvedValue({ id: 7 });
    await userEvent.click(screen.getByRole("button", { name: "Submit with unanswered questions" }));
    await waitFor(() => expect(replace).toHaveBeenCalledWith("/assessment/attempts/7/result"));
  });

  it("reports an answer that could not be saved and retries it", async () => {
    api.fetchAttempt.mockResolvedValue(attempt());
    api.saveAnswer.mockRejectedValueOnce(new Error("offline")).mockResolvedValue({ question_id: 1, selected_option_id: 10, answered: 1 });
    renderWithProviders(<AttemptRunner id={7} />);

    await userEvent.click(await screen.findByLabelText("Option A1"));
    expect(await screen.findByText("One answer wasn't saved.")).toBeInTheDocument();
    await userEvent.click(screen.getByRole("button", { name: "Try again" }));
    expect(await screen.findByText("Answers saved")).toBeInTheDocument();
    expect(api.saveAnswer).toHaveBeenLastCalledWith(7, 1, 10);
  });
});

describe("ResultView", () => {
  const result: AttemptResult = {
    id: 7,
    assessment: { slug: "initial-knowledge", title: "Assessment" },
    started_at: "2026-11-21T00:00:00Z",
    submitted_at: "2026-11-21T00:20:00Z",
    overall: { score: 33, band: "gap", earned: 1, possible: 3, correct: 1, answered: 2, total: 3 },
    domains: [{ domain: { slug: "net", name: "Networking" }, score: 33, band: "gap", earned: 1, possible: 3, correct: 1, answered: 2, total: 3 }],
    strengths: [],
    gaps: ["net"],
    next_steps: [{ kind: "course", reason: "gap", domain: { slug: "net", name: "Networking" }, score: 33, course: { slug: "networking-fundamentals", title: "Networking Fundamentals" } }],
    formula,
    review: [
      { question_id: 1, position: 1, domain: "net", difficulty: 1, prompt: "Right one", code: null, is_correct: true, your_option: { id: 10, label: "Picked right" }, correct_option: null, explanation: null },
      { question_id: 2, position: 2, domain: "net", difficulty: 1, prompt: "Wrong one", code: null, is_correct: false, your_option: { id: 20, label: "Picked wrong" }, correct_option: { id: 21, label: "The key" }, explanation: "Because reasons." },
      { question_id: 3, position: 3, domain: "net", difficulty: 1, prompt: "Skipped one", code: null, is_correct: false, your_option: null, correct_option: { id: 31, label: "Other key" }, explanation: "More reasons." },
    ],
  };

  it("shows scores and reviews only the missed questions, with their answers and explanations", async () => {
    api.fetchResult.mockResolvedValue(result);
    renderWithProviders(<ResultView id={7} />);

    expect(await screen.findByText("Your result", { selector: "h1" })).toBeInTheDocument();
    expect(screen.getAllByText("33%").length).toBeGreaterThan(0);
    expect(screen.getByText("Networking is a gap at 33%.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Start with Networking Fundamentals/ })).toHaveAttribute("href", "/courses/networking-fundamentals");

    expect(screen.queryByText("Right one")).toBeNull();
    expect(screen.getByText("Wrong one")).toBeInTheDocument();
    expect(screen.getByText("The key")).toBeInTheDocument();
    expect(screen.getByText("Because reasons.")).toBeInTheDocument();
    expect(screen.getByText("Skipped one")).toBeInTheDocument();
    expect(screen.getByText("Unanswered")).toBeInTheDocument();
    expect(screen.getByText(/You answered 1 of 3 questions correctly/)).toBeInTheDocument();
  });
});
