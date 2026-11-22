import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { User } from "@/features/auth/types";
import { renderWithProviders } from "@/test/render";
import { Compass } from "./compass";
import { CompassPageProvider, useCompassPageItems } from "./page-items";

const nav = { pathname: "/courses", push: vi.fn() };
vi.mock("next/navigation", () => ({ useRouter: () => ({ push: nav.push, replace: vi.fn(), refresh: vi.fn() }), usePathname: () => nav.pathname }));

const mocks = { me: vi.fn() };
vi.mock("@/features/auth/api", () => ({ fetchCurrentUser: () => mocks.me() }));
vi.mock("@/features/notifications/api", () => ({ fetchUnreadCount: () => Promise.resolve({ count: 3 }) }));
vi.mock("@/features/assessment/api", () => ({ fetchActiveAssessment: () => Promise.resolve({ open_attempt: null, latest_result: { id: 7 } }) }));

const person = (role: "learner" | "admin", compass = true): User => ({
  id: 1,
  name: "Lan",
  email: "lan@example.com",
  role,
  email_verified_at: null,
  created_at: null,
  profile: { avatar_url: null, phone: null, date_of_birth: null, education_level: null, locale: "en", theme: "dark", timezone: null, compass_enabled: compass, shortcuts: {} },
  learning_goal: null,
});

function Page({ sections }: { sections: string[] }) {
  useCompassPageItems(sections.map((s) => ({ id: s, label: s, run: () => {} })));
  return null;
}

/** A page that registers sections until the learner leaves it. */
function Harness() {
  const [shown, setShown] = useState(true);
  return (
    <>
      {shown ? <Page sections={["Why", "Layers"]} /> : null}
      <button type="button" onClick={() => setShown(false)}>
        Leave page
      </button>
      <Compass />
    </>
  );
}

function setup(ui = <Compass />) {
  return renderWithProviders(<CompassPageProvider>{ui}</CompassPageProvider>);
}

async function open(user: ReturnType<typeof userEvent.setup>) {
  await user.click(await screen.findByRole("button", { name: "Compass menu" }));
  return screen.findAllByRole("menuitem");
}

const names = (items: HTMLElement[]) => items.map((i) => i.getAttribute("aria-label")!.split(",")[0]);
const focused = () => document.activeElement?.getAttribute("aria-label");

describe("compass items", () => {
  beforeEach(() => {
    nav.pathname = "/courses";
    nav.push.mockReset();
    localStorage.clear();
  });

  it("shows guests the public routes and the page tools only", async () => {
    mocks.me.mockResolvedValue(null);
    setup();
    expect(names(await open(userEvent.setup()))).toEqual(["Courses", "Careers", "Assessment", "Roadmap", "Labs", "Search", "Scroll to top", "Scroll to bottom"]);
  });

  it("adds the dashboard, notifications and settings for learners, the admin console for admins", async () => {
    mocks.me.mockResolvedValue(person("learner"));
    const user = userEvent.setup();
    setup();
    const learner = names(await open(user));
    expect(learner).toEqual(["Dashboard", "Courses", "Careers", "Assessment", "Roadmap", "Labs", "Notifications", "Settings", "Search", "Scroll to top", "Scroll to bottom"]);
    expect(await screen.findByRole("menuitem", { name: /^Notifications, 7 of 11, 3 unread/ })).toBeInTheDocument();
  });

  it("lists the admin console only for admins", async () => {
    mocks.me.mockResolvedValue(person("admin"));
    setup();
    expect(names(await open(userEvent.setup()))).toContain("Admin console");
  });

  it("is not mounted when switched off, for the account or this browser", async () => {
    mocks.me.mockResolvedValue(person("learner", false));
    setup();
    await waitFor(() => expect(mocks.me).toHaveBeenCalled());
    await new Promise((r) => setTimeout(r, 20));
    expect(screen.queryByRole("button", { name: "Compass menu" })).not.toBeInTheDocument();
  });

  it("shows a page's own sections while the page is mounted", async () => {
    mocks.me.mockResolvedValue(null);
    const user = userEvent.setup();
    setup(<Harness />);
    expect(names(await open(user))).toContain("On this page");
    await user.keyboard("{Escape}");
    await user.click(screen.getByRole("button", { name: "Leave page" }));
    expect(names(await open(user))).not.toContain("On this page");
  });
});

describe("compass keyboard", () => {
  beforeEach(() => {
    nav.pathname = "/settings/security";
    nav.push.mockReset();
    mocks.me.mockResolvedValue(person("learner"));
  });

  it("opens by shortcut on the current page, moves around the ring and into a submenu", async () => {
    const user = userEvent.setup();
    setup();
    const trigger = await screen.findByRole("button", { name: "Compass menu" });
    expect(trigger).toHaveAttribute("aria-haspopup", "menu");

    await user.keyboard("{Alt>}{Shift>}c{/Shift}{/Alt}");
    await screen.findAllByRole("menuitem");
    expect(trigger).toHaveAttribute("aria-expanded", "true");
    // The current section of the site starts focused and is marked.
    await waitFor(() => expect(focused()).toMatch(/^Settings, 8 of 11, has submenu/));
    expect(screen.getByRole("menuitem", { name: /^Settings/ })).toHaveAttribute("aria-current", "page");

    await user.keyboard("{ArrowRight}");
    expect(focused()).toMatch(/^Search/);
    await user.keyboard("{ArrowLeft}{Enter}");
    expect(screen.getByRole("menuitem", { name: /^Settings/ })).toHaveAttribute("aria-expanded", "true");
    expect(screen.getByRole("menu", { name: /^Settings/ })).toBeInTheDocument();
    await waitFor(() => expect(focused()).toMatch(/^Personal information, 1 of 6/));
    expect(screen.getByRole("menuitem", { name: /^Security/ })).toHaveAttribute("aria-current", "page");

    await user.keyboard("{End}");
    expect(focused()).toMatch(/^Keyboard shortcuts, 6 of 6/);
    await user.keyboard("3");
    expect(focused()).toMatch(/^Security/);
    await user.keyboard("p");
    expect(focused()).toMatch(/^Preferences/);
    await user.keyboard("{Enter}");
    expect(nav.push).toHaveBeenCalledWith("/settings/preferences");
    await waitFor(() => expect(trigger).toHaveFocus());
    expect(screen.queryByRole("menu")).not.toBeInTheDocument();
  });

  it("goes back one ring, then closes with Escape and returns focus", async () => {
    const user = userEvent.setup();
    setup();
    const trigger = await screen.findByRole("button", { name: "Compass menu" });
    await user.click(trigger);
    await screen.findAllByRole("menuitem");
    await waitFor(() => expect(focused()).toMatch(/^Settings/));
    await user.keyboard("{Enter}");
    await user.keyboard("{ArrowUp}");
    expect(focused()).toMatch(/^Settings/);
    await user.keyboard("{Enter}{Escape}");
    expect(focused()).toMatch(/^Settings/);
    expect(screen.queryByRole("menu", { name: /^Settings/ })).not.toBeInTheDocument();
    // Tab stays inside the menu.
    await user.keyboard("{Tab}");
    expect(focused()).toMatch(/^Search/);
    await user.keyboard("{Escape}");
    expect(screen.queryByRole("menu")).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });
});
