import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import { SegmentedControl } from "./segmented-control";

function Harness() {
  const [value, setValue] = useState<"light" | "dark" | "system">("system");
  return (
    <SegmentedControl
      label="Theme"
      value={value}
      onChange={setValue}
      options={[
        { value: "light", label: "Light" },
        { value: "dark", label: "Dark" },
        { value: "system", label: "System" },
      ]}
    />
  );
}

describe("SegmentedControl", () => {
  it("exposes a labelled radio group with the current value checked", () => {
    render(<Harness />);

    expect(screen.getByRole("radiogroup", { name: "Theme" })).toBeInTheDocument();
    expect(screen.getByRole("radio", { name: "System" })).toHaveAttribute("aria-checked", "true");
  });

  it("selects on click", async () => {
    render(<Harness />);

    await userEvent.click(screen.getByRole("radio", { name: "Dark" }));

    expect(screen.getByRole("radio", { name: "Dark" })).toHaveAttribute("aria-checked", "true");
  });

  it("moves selection and focus with arrow keys, wrapping at the ends", async () => {
    render(<Harness />);
    const system = screen.getByRole("radio", { name: "System" });
    system.focus();

    await userEvent.keyboard("{ArrowRight}");

    const light = screen.getByRole("radio", { name: "Light" });
    expect(light).toHaveAttribute("aria-checked", "true");
    expect(light).toHaveFocus();
    expect(light).toHaveAttribute("tabindex", "0");
    expect(system).toHaveAttribute("tabindex", "-1");
  });
});
