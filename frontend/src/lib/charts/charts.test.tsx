import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ChartTable, type Datum } from "./chart-parts";
import { ColumnChart } from "./column-chart";
import { LineChart } from "./line-chart";

const data: Datum[] = [
  { key: "a", label: "Nov 2", detail: "Week of 2 November", value: 0, display: "0 min" },
  { key: "b", label: "Nov 9", detail: "Week of 9 November", value: 45, display: "45 min" },
  { key: "c", label: "Nov 16", detail: "Week of 16 November", value: 120, display: "2 h" },
];

describe("charts", () => {
  beforeEach(() => {
    // jsdom has no layout: give charts a width so they draw.
    vi.spyOn(HTMLElement.prototype, "getBoundingClientRect").mockReturnValue({ width: 600, height: 200, left: 0, top: 0, right: 600, bottom: 200, x: 0, y: 0, toJSON: () => ({}) });
  });
  afterEach(() => vi.restoreAllMocks());

  it("draws one column per non-zero value with a labelled reference line", () => {
    const { container } = render(<ColumnChart label="Minutes per week" data={data} formatTick={(v) => `${v}`} reference={{ value: 90, label: "Goal 1 h 30 min" }} />);

    expect(container.querySelectorAll("path.fill-accent[d^='M']")).toHaveLength(2);
    expect(screen.getByText("Goal 1 h 30 min")).toBeInTheDocument();
    expect(screen.getByText("Nov 16")).toBeInTheDocument();
  });

  it("is one tab stop whose arrow keys read each value aloud", async () => {
    const user = userEvent.setup();
    render(<ColumnChart label="Minutes per week" data={data} formatTick={(v) => `${v}`} />);
    const chart = screen.getByRole("group", { name: "Minutes per week" });

    await user.tab();
    expect(chart).toHaveFocus();
    expect(within(chart).getByText("Week of 16 November: 2 h")).toBeInTheDocument();

    await user.keyboard("{ArrowLeft}");
    expect(within(chart).getByText("Week of 9 November: 45 min")).toBeInTheDocument();
    await user.keyboard("{Home}");
    expect(within(chart).getByText("Week of 2 November: 0 min")).toBeInTheDocument();

    await user.tab();
    expect(within(chart).queryByText(/Week of/)).not.toBeInTheDocument();
  });

  it("labels the last point of a line with its value", () => {
    const { container } = render(<LineChart label="Lessons" data={data} formatTick={(v) => `${v}`} />);

    expect(container.querySelector("path.stroke-accent")?.getAttribute("d")).toMatch(/^M44,\S+ L\S+ L\d+(\.\d+)?,\S+$/);
    expect(within(container.querySelector("svg") as unknown as HTMLElement).getByText("2 h")).toBeInTheDocument();
  });

  it("offers every value as a table", async () => {
    const user = userEvent.setup();
    render(<ChartTable data={data} text={{ toggle: "Show as a table", columns: ["Week", "Studied"] }} />);

    await user.click(screen.getByText("Show as a table"));
    const rows = screen.getAllByRole("row");
    expect(rows).toHaveLength(4);
    expect(within(rows[2]).getByRole("rowheader")).toHaveTextContent("Week of 9 November");
    expect(within(rows[2]).getByRole("cell")).toHaveTextContent("45 min");
  });
});
