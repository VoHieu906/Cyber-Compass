import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { LessonSection } from "../types";
import { Section } from "./sections";

const prose = (sources?: LessonSection["sources"]): LessonSection => ({
  id: "a1b2c3d4-0000-4000-8000-000000000001",
  type: "prose",
  heading: "Why layers",
  payload: { text: "Each layer serves the one above." },
  sources,
});

describe("Section sources (D68)", () => {
  it("lists the sources in a collapsed disclosure under the section", async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <Section
        section={prose([
          { label: "RFC 1122", url: "https://www.rfc-editor.org/rfc/rfc1122", kind: "rfc" },
          {
            label: "A forum answer",
            url: "https://serverfault.com/q/1",
            kind: "community",
            note: "needs verification",
          },
        ])}
      />,
    );

    const summary = screen.getByText("Sources (2)");
    expect(screen.getByRole("link", { name: /RFC 1122/ })).not.toBeVisible();
    await user.click(summary);
    expect(screen.getByRole("link", { name: /RFC 1122/ })).toHaveAttribute(
      "href",
      "https://www.rfc-editor.org/rfc/rfc1122",
    );
    expect(screen.getByRole("link", { name: /RFC 1122/ })).toHaveAttribute("rel", "noopener noreferrer");
    expect(screen.getByText(/Community thread/)).toBeInTheDocument();
    expect(screen.getByText(/needs verification/)).toBeInTheDocument();
  });

  it("shows nothing for a section without sources, including older snapshots", () => {
    renderWithProviders(<Section section={prose(null)} />);
    renderWithProviders(<Section section={prose()} />);
    expect(screen.queryByText(/Sources/)).not.toBeInTheDocument();
  });
});

describe("callout tones (D69)", () => {
  const callout = (tone: "recap" | "fun-fact" | "tip", heading: string | null): LessonSection => ({
    id: "a1b2c3d4-0000-4000-8000-000000000002",
    type: "callout",
    heading,
    payload: { text: "Think of nested envelopes.", tone },
  });

  it("names the recap and fun fact tones on screen", () => {
    renderWithProviders(<Section section={callout("recap", null)} />);
    expect(screen.getByText("In other words")).toBeVisible();
    renderWithProviders(<Section section={callout("fun-fact", "Postal mail")} />);
    expect(screen.getByText("Fun fact:", { exact: false })).toBeVisible();
    expect(screen.getByText("Postal mail")).toBeVisible();
  });

  it("keeps the other tones' names for screen readers only", () => {
    renderWithProviders(<Section section={callout("tip", "Shortcut")} />);
    expect(screen.getByText("Tip:", { exact: false })).toHaveClass("sr-only");
  });
});
