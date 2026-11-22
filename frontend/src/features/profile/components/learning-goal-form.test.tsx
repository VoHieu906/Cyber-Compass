import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { User } from "@/features/auth/types";
import { renderWithProviders } from "@/test/render";
import { LearningGoalForm } from "./learning-goal-form";

const update = vi.fn();
vi.mock("../api", () => ({ updateLearningGoal: (input: unknown) => update(input) }));
vi.mock("@/features/careers/api", () => ({
  fetchCareers: async () => [{ slug: "soc-analyst", title: "SOC Analyst", summary: "", domains: [], topic_count: 9 }],
}));
vi.mock("@/features/catalog/api", () => ({
  fetchDomains: async () => [
    { slug: "networking", name: "Networking", description: "", course_count: 1 },
    { slug: "linux", name: "Linux", description: "", course_count: 1 },
  ],
}));

const user: User = {
  id: 1,
  name: "Lan",
  email: "lan@example.com",
  role: "learner",
  email_verified_at: null,
  created_at: null,
  profile: { avatar_url: null, phone: null, date_of_birth: null, education_level: null, locale: "en", theme: "dark", timezone: null, compass_enabled: true, shortcuts: {} },
  learning_goal: null,
};

describe("LearningGoalForm", () => {
  beforeEach(() => update.mockReset());

  it("refuses weekly time out of range without sending", async () => {
    renderWithProviders(<LearningGoalForm user={user} />);
    await userEvent.type(screen.getByLabelText("Study time per week (hours)"), "100");
    await userEvent.click(screen.getByRole("button", { name: "Save goals" }));

    expect(await screen.findByText("Enter between 0.5 and 40 hours.")).toBeInTheDocument();
    expect(update).not.toHaveBeenCalled();
  });

  it("sends the goal with slugs and minutes", async () => {
    update.mockResolvedValue({ ...user, learning_goal: { career: { slug: "soc-analyst", title: "SOC Analyst" }, self_level: "beginner", weekly_minutes: 150, domains: ["linux"] } });
    renderWithProviders(<LearningGoalForm user={user} />);

    await waitFor(() => expect(screen.getByRole("option", { name: "SOC Analyst" })).toBeInTheDocument());
    await userEvent.selectOptions(screen.getByLabelText("Career goal"), "soc-analyst");
    await userEvent.click(screen.getByRole("radio", { name: "Beginner" }));
    await userEvent.type(screen.getByLabelText("Study time per week (hours)"), "2.5");
    await userEvent.click(await screen.findByLabelText("Linux"));
    await userEvent.click(screen.getByRole("button", { name: "Save goals" }));

    await waitFor(() =>
      expect(update).toHaveBeenCalledWith({ career: "soc-analyst", self_level: "beginner", weekly_minutes: 150, domains: ["linux"] }),
    );
    expect(await screen.findByText(/Update your roadmap to use the new career/)).toBeInTheDocument();
  });
});
