import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { User } from "@/features/auth/types";
import { renderWithProviders } from "@/test/render";
import type { Activity, AssessmentHistoryItem, ProgressSummary, WeeklyActivity } from "../types";
import { DashboardView } from "./dashboard-view";
import { runningTotals } from "./format";
import { TimezoneSync } from "./timezone";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn() }), usePathname: () => "/dashboard" }));

const mocks = {
  me: vi.fn(),
  preferences: vi.fn(),
  summary: vi.fn(),
  activity: vi.fn(),
  skills: vi.fn(),
  recent: vi.fn(),
  next: vi.fn(),
  courses: vi.fn(),
  assessments: vi.fn(),
};
vi.mock("@/features/auth/api", () => ({ fetchCurrentUser: () => mocks.me() }));
vi.mock("@/features/profile/api", () => ({ updatePreferences: (input: unknown) => mocks.preferences(input) }));
vi.mock("../api", () => ({
  fetchSummary: () => mocks.summary(),
  fetchActivity: (range: string) => mocks.activity(range),
  fetchSkills: () => mocks.skills(),
  fetchRecent: () => mocks.recent(),
  fetchNext: () => mocks.next(),
  fetchCourses: () => mocks.courses(),
  fetchAssessments: () => mocks.assessments(),
}));

const me = (timezone: string | null): User => ({
  id: 1,
  name: "Lan Nguyen",
  email: "lan@example.com",
  role: "learner",
  email_verified_at: null,
  created_at: null,
  profile: { avatar_url: null, phone: null, date_of_birth: null, education_level: null, locale: "en", theme: "dark", timezone },
  learning_goal: null,
});

const summary: ProgressSummary = {
  enrolled_courses: 2,
  completed_courses: 1,
  completed_lessons: 9,
  total_minutes: 310,
  week: { starts_on: "2026-11-16", minutes: 45, goal_minutes: 120 },
  streak: { current: 3, longest: 5, active_today: false, last_active_on: "2026-11-20" },
  roadmap: { career: "SOC Analyst", completion_percent: 40 },
  timezone: "Asia/Ho_Chi_Minh",
};

const weeks = (range: string): WeeklyActivity => ({
  timezone: "Asia/Ho_Chi_Minh",
  lessons_completed_before: 4,
  weeks: [
    { starts_on: "2026-11-02", minutes: 0, lessons_completed: 0 },
    { starts_on: "2026-11-09", minutes: 150, lessons_completed: 3 },
    { starts_on: "2026-11-16", minutes: 45, lessons_completed: 2 },
  ].slice(range === "4w" ? 1 : 0),
});

const recent: Activity[] = [
  { id: 3, type: "exercise_submitted", occurred_at: "2026-11-20T09:00:00Z", minutes: 12, subject: { kind: "exercise", lesson: { slug: "cia-triad", title: "The CIA triad" }, score: 80, passed: true } },
  { id: 2, type: "lesson_completed", occurred_at: "2026-11-19T09:00:00Z", minutes: 0, subject: { kind: "lesson", slug: "osi-model", title: "The OSI model" } },
  { id: 1, type: "lab_completed", occurred_at: "2026-11-18T09:00:00Z", minutes: 0, subject: null },
];

const attempt = (id: number, score: number, band: AssessmentHistoryItem["band"]): AssessmentHistoryItem => ({
  id,
  assessment: { slug: "skills", title: "Skills assessment" },
  submitted_at: `2026-11-1${id}T10:00:00Z`,
  score,
  band,
  domains: [],
});

function fill() {
  mocks.me.mockResolvedValue(me("Asia/Ho_Chi_Minh"));
  mocks.summary.mockResolvedValue(summary);
  mocks.activity.mockImplementation((range: string) => Promise.resolve(weeks(range)));
  mocks.skills.mockResolvedValue([
    { slug: "networking", name: "Networking", score: 82, band: "strength", completion: { completed: 2, total: 6, percent: 33 } },
    { slug: "linux", name: "Linux", score: null, band: null, completion: { completed: 0, total: 0, percent: 0 } },
  ]);
  mocks.recent.mockResolvedValue(recent);
  mocks.next.mockResolvedValue({ source: "roadmap", lessons: [{ slug: "threats", title: "Threats and risk", topic: "Security principles", estimated_minutes: 18 }] });
  mocks.courses.mockResolvedValue([
    { slug: "networking-fundamentals", title: "Networking Fundamentals", domain: null, enrolled_at: "2026-11-01T00:00:00Z", completed_at: null, completed_lessons: 3, total_lessons: 6, percent: 50 },
  ]);
  mocks.assessments.mockResolvedValue([attempt(1, 40, "gap"), attempt(2, 53, "developing")]);
}

describe("dashboard", () => {
  beforeEach(() => Object.values(mocks).forEach((m) => m.mockReset()));

  it("sums up where I stand and what keeps the streak going", async () => {
    fill();
    renderWithProviders(<DashboardView />);

    expect(await screen.findByRole("heading", { level: 1, name: "Welcome back, Lan" })).toBeInTheDocument();
    expect(await screen.findByText("3 days in a row. Study today to keep it going.")).toBeInTheDocument();
    const figure = (label: string) => screen.getByText(label, { selector: "dt" }).parentElement!;
    expect(figure("Streak")).toHaveTextContent("3 daysLongest 5 days");
    expect(figure("This week")).toHaveTextContent("45 minof your 2 h goal");
    expect(figure("Lessons completed")).toHaveTextContent("9");
    expect(figure("Courses completed")).toHaveTextContent("1 of 2");
    expect(figure("Total study time")).toHaveTextContent("5 h 10 min");
    expect(within(figure("Roadmap")).getByRole("link", { name: "SOC Analyst" })).toHaveAttribute("href", "/roadmap");
  });

  it("links the next lesson, recent activity, skills, courses and assessment history", async () => {
    fill();
    renderWithProviders(<DashboardView />);

    const next = await screen.findByRole("region", { name: "Study next" });
    expect(await within(next).findByText("From your roadmap")).toBeInTheDocument();
    expect(within(next).getByRole("link", { name: /Start/ })).toHaveAttribute("href", "/learn/threats");

    const feed = screen.getByRole("region", { name: "Recent activity" });
    expect(await within(feed).findByRole("link", { name: "The CIA triad" })).toHaveAttribute("href", "/learn/cia-triad/exercise");
    expect(within(feed).getByText(/with 80%/)).toBeInTheDocument();
    expect(within(feed).getByText("Something that is no longer available")).toBeInTheDocument();

    const skills = screen.getByRole("region", { name: "Skills by domain" });
    expect(await within(skills).findByText("82%")).toBeInTheDocument();
    expect(within(skills).getByText("Not assessed")).toBeInTheDocument();

    expect(await screen.findByText("3 of 6 lessons")).toBeInTheDocument();

    const history = screen.getByRole("region", { name: "Assessment results" });
    expect(await within(history).findByText("2 attempts, from 40% to 53%.")).toBeInTheDocument();
    expect(within(history).getByRole("group", { name: "Overall score per attempt" })).toBeInTheDocument();
  });

  it("draws study over time and changes the range for both charts at once", async () => {
    fill();
    const user = userEvent.setup();
    renderWithProviders(<DashboardView />);

    expect(await screen.findByText("3 h 15 min over the last 3 weeks; your weekly goal was met in 1 week.")).toBeInTheDocument();
    expect(screen.getByText("5 new lessons in the last 3 weeks; 9 in all.")).toBeInTheDocument();
    expect(mocks.activity).toHaveBeenLastCalledWith("12w");

    await user.click(screen.getByRole("radio", { name: "4 weeks" }));
    expect(mocks.activity).toHaveBeenLastCalledWith("4w");
    expect(await screen.findByText("3 h 15 min over the last 2 weeks; your weekly goal was met in 1 week.")).toBeInTheDocument();
  });

  it("guides a new learner instead of showing empty charts", async () => {
    mocks.me.mockResolvedValue(me("UTC"));
    mocks.summary.mockResolvedValue({ ...summary, enrolled_courses: 0, completed_courses: 0, completed_lessons: 0, total_minutes: 0, week: { starts_on: "2026-11-16", minutes: 0, goal_minutes: null }, streak: { current: 0, longest: 0, active_today: false, last_active_on: null }, roadmap: null });
    mocks.activity.mockResolvedValue({ timezone: "UTC", lessons_completed_before: 0, weeks: [{ starts_on: "2026-11-16", minutes: 0, lessons_completed: 0 }] });
    mocks.skills.mockResolvedValue([]);
    mocks.recent.mockResolvedValue([]);
    mocks.next.mockResolvedValue({ source: null, lessons: [] });
    mocks.courses.mockResolvedValue([]);
    mocks.assessments.mockResolvedValue([]);
    renderWithProviders(<DashboardView />);

    expect(await screen.findByText("Study for a few minutes today to start a streak.")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Set a weekly goal" })).toHaveAttribute("href", "/settings/goals");
    expect(screen.getByRole("link", { name: "Build your roadmap" })).toHaveAttribute("href", "/roadmap");
    expect(await screen.findByRole("link", { name: /Take the assessment/ })).toHaveAttribute("href", "/assessment");
    expect(await screen.findByText("Nothing yet. What you study shows up here.")).toBeInTheDocument();
    expect(await screen.findByRole("link", { name: "Find one in the catalog" })).toHaveAttribute("href", "/courses");
  });

  it("adds up running totals from the lessons before the range", () => {
    expect(runningTotals(4, [0, 3, 2])).toEqual([4, 7, 9]);
    expect(runningTotals(0, [])).toEqual([]);
  });
});

describe("timezone", () => {
  beforeEach(() => Object.values(mocks).forEach((m) => m.mockReset()));

  it("saves the browser's timezone once when my account has none", async () => {
    mocks.me.mockResolvedValue(me(null));
    mocks.preferences.mockResolvedValue(me("Asia/Ho_Chi_Minh"));
    vi.spyOn(Intl.DateTimeFormat.prototype, "resolvedOptions").mockReturnValue({ timeZone: "Asia/Ho_Chi_Minh" } as Intl.ResolvedDateTimeFormatOptions);
    renderWithProviders(<TimezoneSync />);

    await waitFor(() => expect(mocks.preferences).toHaveBeenCalledWith({ timezone: "Asia/Ho_Chi_Minh" }));
    expect(mocks.preferences).toHaveBeenCalledTimes(1);
    vi.restoreAllMocks();
  });

  it("never overwrites a timezone I already have", async () => {
    mocks.me.mockResolvedValue(me("Europe/Paris"));
    renderWithProviders(<TimezoneSync />);

    await waitFor(() => expect(mocks.me).toHaveBeenCalled());
    expect(mocks.preferences).not.toHaveBeenCalled();
  });
});
