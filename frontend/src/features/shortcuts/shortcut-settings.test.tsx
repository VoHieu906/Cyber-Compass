import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { User } from "@/features/auth/types";
import { renderWithProviders } from "@/test/render";
import { ShortcutsProvider } from "./prefs";
import { ShortcutSettings } from "./shortcut-settings";

const mocks = { me: vi.fn(), update: vi.fn() };
vi.mock("@/features/auth/api", () => ({ fetchCurrentUser: () => mocks.me() }));
vi.mock("@/features/profile/api", () => ({ updatePreferences: (input: unknown) => mocks.update(input) }));

const person = (role: "learner" | "admin", shortcuts: User["profile"]["shortcuts"] = {}): User => ({
  id: 1,
  name: "Lan",
  email: "lan@example.com",
  role,
  email_verified_at: null,
  created_at: null,
  profile: { avatar_url: null, phone: null, date_of_birth: null, education_level: null, locale: "en", theme: "dark", timezone: null, compass_enabled: true, shortcuts },
  learning_goal: null,
});

function setup() {
  return renderWithProviders(
    <ShortcutsProvider>
      <ShortcutSettings />
    </ShortcutsProvider>,
  );
}

describe("keyboard shortcuts tab", () => {
  beforeEach(() => {
    mocks.update.mockReset();
    mocks.update.mockImplementation(async (input: { shortcuts: User["profile"]["shortcuts"] }) => person("learner", input.shortcuts));
  });

  it("lists the shortcuts by group, without the editor for learners", async () => {
    mocks.me.mockResolvedValue(person("learner"));
    setup();
    expect(await screen.findByRole("heading", { name: "General" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Search" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Inside the compass menu" })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Lesson editor" })).not.toBeInTheDocument();
    // Menu keys are listed, not rebindable.
    expect(screen.queryByRole("button", { name: "Change the shortcut for Next item" })).not.toBeInTheDocument();
  });

  it("shows the editor shortcut to admins", async () => {
    mocks.me.mockResolvedValue(person("admin"));
    setup();
    expect(await screen.findByRole("heading", { name: "Lesson editor" })).toBeInTheDocument();
  });

  it("refuses taken and reserved keys, then saves a free combination", async () => {
    mocks.me.mockResolvedValue(person("learner"));
    const user = userEvent.setup();
    setup();
    await user.click(await screen.findByRole("button", { name: "Change the shortcut for Open or close the compass" }));
    const recorder = screen.getByRole("button", { name: /Press the new keys for Open or close the compass/ });
    expect(recorder).toHaveFocus();

    await user.keyboard("{Control>}k{/Control}");
    expect(await screen.findByText("Ctrl+K is already used for “Open search (from anywhere)”.")).toBeInTheDocument();
    await user.keyboard("{Control>}t{/Control}");
    expect(await screen.findByText(/Ctrl\+T belongs to the browser or the system/)).toBeInTheDocument();
    expect(mocks.update).not.toHaveBeenCalled();

    await user.keyboard("{Alt>}{Shift>}j{/Shift}{/Alt}");
    await waitFor(() => expect(mocks.update).toHaveBeenCalledWith({ shortcuts: { "compass.toggle": { code: "KeyJ", alt: true, shift: true } } }));
    expect(await screen.findByText("Shortcuts saved.")).toBeInTheDocument();
  });

  it("turns a shortcut off and resets everything", async () => {
    mocks.me.mockResolvedValue(person("learner", { "search.open": { key: "s" } }));
    const user = userEvent.setup();
    setup();
    await user.click(await screen.findByRole("button", { name: "Turn off the shortcut for Open search (from anywhere)" }));
    await waitFor(() => expect(mocks.update).toHaveBeenLastCalledWith({ shortcuts: { "search.open": { key: "s" }, "search.openCombo": null } }));
    await user.click(screen.getByRole("button", { name: "Reset all shortcuts" }));
    await waitFor(() => expect(mocks.update).toHaveBeenLastCalledWith({ shortcuts: {} }));
  });
});
