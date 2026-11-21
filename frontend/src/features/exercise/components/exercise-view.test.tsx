import { fireEvent, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { Lesson } from "@/features/learning/types";
import { ApiError } from "@/lib/api/client";
import { renderWithProviders } from "@/test/render";
import type { Exercise, ExerciseResult } from "../types";
import { ExerciseView } from "./exercise-view";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }), usePathname: () => "/learn/osi-model/exercise" }));
const mocks = { lesson: vi.fn(), exercise: vi.fn(), submit: vi.fn() };
vi.mock("@/features/learning/api", () => ({ fetchLesson: () => mocks.lesson(), startLesson: vi.fn(), completeLesson: vi.fn() }));
vi.mock("../api", () => ({ fetchExercise: () => mocks.exercise(), submitExercise: (...args: unknown[]) => mocks.submit(...args) }));

const lesson = {
  slug: "osi-model",
  title: "The OSI model",
  summary: "",
  estimated_minutes: 15,
  objectives: ["List the seven OSI layers in order"],
  key_takeaways: ["Each layer adds its own header."],
  resources: [],
  course: { slug: "networking-fundamentals", title: "Networking Fundamentals", domain: "Networking" },
  chapter: { title: "Models", position: 1 },
  prerequisites: [],
  sections: [
    { id: "sec-layers", type: "prose", heading: "The seven layers", payload: { text: "Seven layers, physical to application." } },
    { id: "sec-encap", type: "prose", heading: "Encapsulation", payload: { text: "Each layer wraps the one above." } },
  ],
  navigation: { position: 2, total: 6, previous: null, next: { slug: "tcp-ip-model", title: "The TCP/IP model" } },
  progress: { status: "started", completed_at: null },
  exercise: { question_count: 7, pass_percent: 70, passed: false },
  labs: [],
} satisfies Lesson;

const layers = { id: "sec-layers", heading: "The seven layers" };
const exercise: Exercise = {
  lesson: { slug: "osi-model", title: "The OSI model" },
  pass_percent: 70,
  question_count: 7,
  passed: false,
  best_score: null,
  attempt_count: 0,
  questions: [
    { id: 1, position: 1, type: "single_choice", prompt: "Which layer routes packets?", section: layers, payload: { options: [{ id: 0, label: "Network" }, { id: 1, label: "Session" }] } },
    { id: 2, position: 2, type: "multi_choice", prompt: "Which are transport protocols?", section: layers, payload: { options: [{ id: 0, label: "TCP" }, { id: 1, label: "UDP" }, { id: 2, label: "IP" }] } },
    { id: 3, position: 3, type: "true_false", prompt: "There are seven layers.", section: layers, payload: {} },
    { id: 4, position: 4, type: "fill_blank", prompt: "Name the units.", section: { id: "sec-encap", heading: "Encapsulation" }, payload: { text: "A ___ and a ___.", blanks: 2 } },
    { id: 5, position: 5, type: "matching", prompt: "Match them.", section: layers, payload: { left: [{ id: 0, label: "HTTP" }, { id: 1, label: "IP" }], right: [{ id: 0, label: "Layer 3" }, { id: 1, label: "Layer 7" }] } },
    { id: 6, position: 6, type: "ordering", prompt: "Order them.", section: layers, payload: { items: [{ id: 0, label: "Network" }, { id: 1, label: "Physical" }, { id: 2, label: "Data link" }] } },
    { id: 7, position: 7, type: "scenario", prompt: "Which layer?", section: layers, payload: { scenario: "SYN packets flood a server.", options: [{ id: 0, label: "Transport" }, { id: 1, label: "Physical" }] } },
  ],
};

const result: ExerciseResult = {
  id: 9,
  lesson: exercise.lesson,
  score: 86,
  passed: true,
  pass_percent: 70,
  correct: 6,
  total: 7,
  submitted_at: "2026-11-21T10:00:00Z",
  lesson_status: "completed",
  questions: exercise.questions.map((q) =>
    q.id === 4
      ? { ...q, response: { blanks: ["segment", "cell"] }, is_correct: false, correct_answer: { blanks: [["segment"], ["frame", "Ethernet frame"]] }, explanation: "Frames live at layer 2." }
      : { ...q, response: null, is_correct: true, correct_answer: null, explanation: "Because." },
  ),
};

function stubWide() {
  vi.stubGlobal("matchMedia", (query: string) => ({ matches: query.includes("min-width"), addEventListener: vi.fn(), removeEventListener: vi.fn() }));
}

describe("ExerciseView", () => {
  beforeEach(() => {
    Object.values(mocks).forEach((m) => m.mockReset());
    mocks.lesson.mockResolvedValue(lesson);
    mocks.exercise.mockResolvedValue(exercise);
    mocks.submit.mockResolvedValue(result);
    window.sessionStorage.clear();
  });
  afterEach(() => vi.unstubAllGlobals());

  it("takes every question type by keyboard and pointer, submits only the answers, and shows the result", async () => {
    stubWide();
    const user = userEvent.setup();
    renderWithProviders(<ExerciseView slug="osi-model" />);

    // The theory panel shows the section that teaches the current question.
    const theory = await screen.findByRole("complementary", { name: "Theory" });
    expect(within(theory).getByRole("heading", { name: "The seven layers" })).toBeInTheDocument();
    expect(within(theory).getByText("Each layer adds its own header.")).toBeInTheDocument();

    await user.click(screen.getByRole("radio", { name: "Network" }));
    await user.click(screen.getByRole("button", { name: /^Next/ }));
    await user.click(screen.getByRole("checkbox", { name: "TCP" }));
    await user.click(screen.getByRole("checkbox", { name: "UDP" }));
    await user.click(screen.getByRole("button", { name: /^Next/ }));
    await user.click(screen.getByRole("radio", { name: "True" }));
    await user.click(screen.getByRole("button", { name: /^Next/ }));

    expect(within(theory).getByRole("heading", { name: "Encapsulation" })).toBeInTheDocument();
    await user.type(screen.getByRole("textbox", { name: "Blank 1 of 2" }), "segment");
    await user.type(screen.getByRole("textbox", { name: "Blank 2 of 2" }), "cell");
    await user.click(screen.getByRole("button", { name: /^Next/ }));

    await user.selectOptions(screen.getByRole("combobox", { name: "HTTP" }), "Layer 7");
    await user.selectOptions(screen.getByRole("combobox", { name: "IP" }), "Layer 3");
    await user.click(screen.getByRole("button", { name: /^Next/ }));

    // Ordering by keyboard: focus stays on the moved item and the move is announced.
    const down = screen.getByRole("button", { name: "Move Network down" });
    down.focus();
    await user.keyboard("{Enter}");
    expect(await screen.findByText("Network moved to position 2 of 3.")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Move Physical up" }));
    await user.click(screen.getByRole("button", { name: /^Next/ }));

    // Question 7 is left unanswered.
    expect(screen.getByText("SYN packets flood a server.")).toBeInTheDocument();
    await user.click(screen.getAllByRole("button", { name: /^Review and submit/ }).at(-1)!);
    expect(screen.getByText("1 question has no answer and will count as wrong.")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Submit anyway" }));

    const [slug, answers] = mocks.submit.mock.calls[0];
    expect(slug).toBe("osi-model");
    expect(answers).toEqual([
      { question_id: 1, response: { option: 0 } },
      { question_id: 2, response: { options: [0, 1] } },
      { question_id: 3, response: { value: true } },
      { question_id: 4, response: { blanks: ["segment", "cell"] } },
      { question_id: 5, response: { matches: [1, 0] } },
      { question_id: 6, response: { order: [1, 0, 2] } },
    ]);

    // The result: score, the next lesson, the wrong answer with the key, and its theory.
    expect(await screen.findByRole("heading", { name: "Passed with 86%" })).toHaveFocus();
    expect(screen.getByRole("link", { name: /Next: The TCP\/IP model/ })).toHaveAttribute("href", "/learn/tcp-ip-model");
    const wrong = screen.getByText("Name the units.").closest("li")!;
    expect(within(wrong).getByText("Incorrect")).toBeInTheDocument();
    expect(within(wrong).getByText("frame or Ethernet frame")).toBeInTheDocument();
    await user.click(within(wrong).getByRole("button", { name: "Read the theory: Encapsulation" }));
    expect(within(theory).getByRole("heading", { name: "Encapsulation" })).toBeInTheDocument();
    expect(window.sessionStorage.getItem("exercise-draft:osi-model")).toBe("{}");
    // Drives seven question types through the real inputs; under a full parallel run it can pass 5 s.
  }, 15_000);

  it("uses tabs for the exercise and the theory on narrow screens", async () => {
    const user = userEvent.setup();
    renderWithProviders(<ExerciseView slug="osi-model" />);

    const tabs = await screen.findByRole("tablist", { name: "Exercise and theory" });
    expect(screen.getByRole("heading", { name: "Which layer routes packets?" })).toBeInTheDocument();
    await user.click(within(tabs).getByRole("tab", { name: "Theory" }));
    expect(screen.getByRole("heading", { name: "The seven layers" })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Which layer routes packets?" })).toBeNull();
  });

  it("keeps the draft through a reload", async () => {
    const user = userEvent.setup();
    const first = renderWithProviders(<ExerciseView slug="osi-model" />);
    await user.click(await screen.findByRole("radio", { name: "Session" }));
    first.unmount();

    renderWithProviders(<ExerciseView slug="osi-model" />);
    expect(await screen.findByRole("radio", { name: "Session" })).toBeChecked();
    expect(screen.getByText("1 of 7 answered")).toBeInTheDocument();
  });

  it("offers a retry after a failed attempt, starting from a clean draft", async () => {
    mocks.submit.mockResolvedValue({ ...result, score: 43, passed: false, correct: 3, lesson_status: "started" });
    const user = userEvent.setup();
    renderWithProviders(<ExerciseView slug="osi-model" />);

    await user.click(await screen.findByRole("radio", { name: "Network" }));
    await user.click(screen.getAllByRole("button", { name: /^Review and submit/ }).at(-1)!);
    fireEvent.click(screen.getByRole("button", { name: "Submit anyway" }));
    expect(await screen.findByRole("heading", { name: "Not passed yet: 43%" })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Try again" }));
    expect(screen.getByRole("radio", { name: "Network" })).not.toBeChecked();
    expect(screen.getByText("0 of 7 answered")).toBeInTheDocument();
  });

  it("says so when the lesson has no exercise", async () => {
    mocks.exercise.mockRejectedValue(new ApiError(404, { message: "Not found" }));
    renderWithProviders(<ExerciseView slug="hashing-and-passwords" />);

    expect(await screen.findByText("This lesson has no exercise.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Back to the lesson/ })).toHaveAttribute("href", "/learn/hashing-and-passwords");
  });
});
