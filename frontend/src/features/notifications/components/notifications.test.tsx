import { act, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { User } from "@/features/auth/types";
import { renderWithProviders } from "@/test/render";
import { UNREAD_POLL_MS } from "../hooks";
import type { AppNotification, NotificationPage } from "../types";
import { NotificationBell } from "./notification-bell";
import { NotificationSettings } from "./notification-settings";
import { NotificationsView } from "./notifications-view";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn() }), usePathname: () => "/notifications" }));

const mocks = { me: vi.fn(), list: vi.fn(), count: vi.fn(), read: vi.fn(), readAll: vi.fn(), remove: vi.fn(), prefs: vi.fn(), savePrefs: vi.fn() };
vi.mock("@/features/auth/api", () => ({ fetchCurrentUser: () => mocks.me() }));
vi.mock("../api", () => ({
  fetchNotifications: (page: number, unread: boolean, perPage: number) => mocks.list(page, unread, perPage),
  fetchUnreadCount: () => mocks.count(),
  markRead: (id: string) => mocks.read(id),
  markAllRead: () => mocks.readAll(),
  deleteNotification: (id: string) => mocks.remove(id),
  fetchPreferences: () => mocks.prefs(),
  updatePreferences: (p: unknown) => mocks.savePrefs(p),
}));

const user: User = {
  id: 1,
  name: "Lan",
  email: "lan@example.com",
  role: "learner",
  email_verified_at: null,
  created_at: null,
  profile: { avatar_url: null, phone: null, date_of_birth: null, education_level: null, locale: "en", theme: "dark", timezone: "UTC", compass_enabled: true, shortcuts: {} },
  learning_goal: null,
};

const n = (id: string, type: AppNotification["type"], params: AppNotification["params"], read = false, url: string | null = "/dashboard"): AppNotification => ({
  id,
  type,
  params,
  url,
  read_at: read ? "2026-11-21T09:00:00Z" : null,
  created_at: "2026-11-21T08:00:00Z",
});

const all = [
  n("a", "milestone", { kind: "streak", days: 7 }),
  n("b", "recommendation", { lesson: "Salting in practice", context: "Applied Cryptography Basics", slug: "salting", via: "course" }, false, "/learn/salting"),
  n("c", "milestone", { kind: "roadmap", percent: 25, title: "SOC Analyst" }, true),
  n("d", "milestone", { kind: "course", title: "Security Fundamentals" }, true),
  n("e", "reminder", { streak: 3 }, true),
  n("f", "reminder", { streak: 0 }, true),
  n("g", "assessment_result", { score: 53, band: "developing", attempt: 4 }, true),
  n("h", "lesson_completed", { lesson: "The OSI model", slug: "osi-model" }, true),
];
const page = (items: AppNotification[], last = 1): NotificationPage => ({ data: items, meta: { current_page: 1, last_page: last, per_page: 20, total: items.length } });

describe("notifications", () => {
  beforeEach(() => {
    Object.values(mocks).forEach((m) => m.mockReset());
    mocks.me.mockResolvedValue(user);
  });
  afterEach(() => vi.useRealTimers());

  it("shows nothing to guests", async () => {
    mocks.me.mockResolvedValue(null);
    renderWithProviders(<NotificationBell />);
    await waitFor(() => expect(mocks.me).toHaveBeenCalled());
    expect(screen.queryByRole("button")).not.toBeInTheDocument();
    expect(mocks.count).not.toHaveBeenCalled();
  });

  it("puts the unread count in the bell's name and caps the badge at 9+", async () => {
    mocks.count.mockResolvedValue({ count: 12 });
    renderWithProviders(<NotificationBell />);

    const bell = await screen.findByRole("button", { name: "Notifications, 12 unread" });
    expect(within(bell).getByText("9+")).toBeInTheDocument();
  });

  it("polls the unread count every minute", async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    mocks.count.mockResolvedValueOnce({ count: 0 }).mockResolvedValue({ count: 2 });
    renderWithProviders(<NotificationBell />);
    expect(await screen.findByRole("button", { name: "Notifications" })).toBeInTheDocument();

    await act(() => vi.advanceTimersByTimeAsync(UNREAD_POLL_MS));
    expect(await screen.findByRole("button", { name: "Notifications, 2 unread" })).toBeInTheDocument();
  });

  it("opens the latest five, marks one read on opening it, and marks all read", async () => {
    mocks.count.mockResolvedValue({ count: 2 });
    mocks.list.mockResolvedValue(page(all.slice(0, 5)));
    mocks.read.mockResolvedValue(all[1]);
    mocks.readAll.mockResolvedValue({ count: 0 });
    const u = userEvent.setup();
    renderWithProviders(<NotificationBell />);

    await u.click(await screen.findByRole("button", { name: "Notifications, 2 unread" }));
    expect(mocks.list).toHaveBeenCalledWith(1, false, 5);
    const link = await screen.findByRole("link", { name: "New lesson for you: Salting in practice, in Applied Cryptography Basics." });
    expect(link).toHaveAttribute("href", "/learn/salting");
    expect(screen.getAllByText("New")).toHaveLength(2);

    await u.click(screen.getByRole("button", { name: "Mark all as read" }));
    expect(mocks.readAll).toHaveBeenCalled();

    expect(screen.getByRole("link", { name: "See all notifications" })).toHaveAttribute("href", "/notifications");
    link.addEventListener("click", (e) => e.preventDefault());
    await u.click(link);
    expect(mocks.read).toHaveBeenCalledWith("b");
    // Opening an item closes the panel.
    expect(screen.getByRole("button", { name: /Notifications/ })).toHaveAttribute("aria-expanded", "false");
  });

  it("words every type in the current language", async () => {
    mocks.count.mockResolvedValue({ count: 2 });
    mocks.list.mockResolvedValue(page(all));
    renderWithProviders(<NotificationsView />);

    for (const text of [
      "7 days in a row. Keep it going.",
      "Your SOC Analyst roadmap is 25% done.",
      "Course completed: Security Fundamentals.",
      "Keep your 3-day streak going: study a few minutes today.",
      "You haven't studied yet today. A short lesson keeps you moving.",
      "Your assessment result is ready: 53%.",
      "You completed The OSI model.",
    ]) {
      expect(await screen.findByText(text)).toBeInTheDocument();
    }
    expect(screen.getByText("2 unread notifications")).toBeInTheDocument();
  });

  it("filters to unread, marks read, deletes and pages", async () => {
    mocks.count.mockResolvedValue({ count: 1 });
    mocks.list.mockImplementation((_p: number, unread: boolean) => Promise.resolve(unread ? page([all[0]]) : page(all, 3)));
    mocks.read.mockResolvedValue(all[0]);
    mocks.remove.mockResolvedValue(undefined);
    const u = userEvent.setup();
    renderWithProviders(<NotificationsView />);

    expect(await screen.findByRole("navigation", { name: "Notification pages" })).toBeInTheDocument();
    await u.click(screen.getByRole("radio", { name: "Unread" }));
    expect(mocks.list).toHaveBeenLastCalledWith(1, true, 20);
    await waitFor(() => expect(screen.getAllByRole("listitem")).toHaveLength(1));

    await u.click(screen.getByRole("button", { name: "Mark as read: 7 days in a row. Keep it going." }));
    expect(mocks.read).toHaveBeenCalledWith("a");
    await u.click(screen.getByRole("button", { name: "Delete: 7 days in a row. Keep it going." }));
    expect(mocks.remove).toHaveBeenCalledWith("a");
  });

  it("says when there is nothing to show", async () => {
    mocks.count.mockResolvedValue({ count: 0 });
    mocks.list.mockResolvedValue(page([]));
    renderWithProviders(<NotificationsView />);

    expect(await screen.findByText("No notifications yet. Lesson completions, milestones and reminders show up here.")).toBeInTheDocument();
    expect(screen.getByText("You're all caught up.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Mark all as read" })).toBeDisabled();
  });

  it("switches a channel per type and saves it", async () => {
    const prefs = ["recommendation", "lesson_completed", "milestone", "reminder", "assessment_result"].map((type) => ({ type, in_app: true, email: false }));
    mocks.prefs.mockResolvedValue(prefs);
    mocks.savePrefs.mockImplementation(([p]: [{ type: string }]) => Promise.resolve(prefs.map((x) => (x.type === p.type ? p : x))));
    const u = userEvent.setup();
    renderWithProviders(<NotificationSettings />);

    const email = await screen.findByRole("checkbox", { name: "Study reminders by email" });
    expect(email).not.toBeChecked();
    await u.click(email);
    expect(mocks.savePrefs).toHaveBeenCalledWith([{ type: "reminder", in_app: true, email: true }]);
    expect(await screen.findByText("Saved.")).toBeInTheDocument();
    expect(screen.getByRole("checkbox", { name: "Study reminders by email" })).toBeChecked();
  });
});
