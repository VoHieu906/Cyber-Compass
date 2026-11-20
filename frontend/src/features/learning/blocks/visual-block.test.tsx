import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import { renderWithProviders } from "@/test/render";
import { Diagram } from "../diagrams/registry";
import { VISUAL_BLOCK_TYPES, VisualBlock } from "./visual-block";

const FIXTURES = resolve(__dirname, "../../../../../backend/app/Modules/Content/Blocks/fixtures");
const load = (name: string): Record<string, unknown>[] => JSON.parse(readFileSync(`${FIXTURES}/${name}`, "utf8"));

describe.each(VISUAL_BLOCK_TYPES)("%s renderer", (type) => {
  it.each(load(`${type}.valid.json`).map((p, i) => [i, p]))("draws valid fixture #%i", async (_i, payload) => {
    const { container } = renderWithProviders(<VisualBlock type={type} payload={payload} />);
    // The graph blocks load on demand; wait for the placeholder to be replaced.
    await waitFor(() => expect(container.querySelector("[class*=animate-pulse]")).toBeNull());
    expect(screen.queryByText("This diagram isn't available.")).toBeNull();
  });

  it("shows a notice instead of breaking on an invalid payload", () => {
    renderWithProviders(<VisualBlock type={type} payload={load(`${type}.invalid.json`)[0]} />);
    expect(screen.getByText("This diagram isn't available.")).toBeInTheDocument();
  });
});

describe("interactions", () => {
  it("shows each actor's state for the current message", () => {
    renderWithProviders(<VisualBlock type="sequence" payload={load("sequence.valid.json")[0]} />);
    expect(screen.getByText("Client state")).toBeInTheDocument();
    expect(screen.getByText("SYN-SENT")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /start over/i })).toBeInTheDocument();
  });

  it("selects a layer with the keyboard", async () => {
    const user = userEvent.setup();
    renderWithProviders(<VisualBlock type="layer-stack" payload={load("layer-stack.valid.json")[1]} />);
    await user.click(screen.getByRole("tab", { name: /Application/ }));
    await user.keyboard("{ArrowDown}");
    expect(screen.getByRole("tab", { name: /Physical/ })).toHaveAttribute("aria-selected", "true");
    expect(screen.getByRole("heading", { name: "Physical" })).toBeInTheDocument();
  });

  it("marks changed values in a step-through", async () => {
    const user = userEvent.setup();
    renderWithProviders(<VisualBlock type="step-through" payload={load("step-through.valid.json")[1]} />);
    await user.click(screen.getByRole("button", { name: /next step/i }));
    expect(screen.getByRole("heading", { name: "Segment" })).toBeInTheDocument();
    expect(screen.getByText("(changed)").parentElement).toHaveTextContent("Segment");
  });

  it("checks a choose-all answer only when every correct option is chosen", async () => {
    const user = userEvent.setup();
    renderWithProviders(<VisualBlock type="knowledge-check" payload={load("knowledge-check.valid.json")[1]} />);
    expect(screen.getByText("Choose all that apply.")).toBeInTheDocument();
    await user.click(screen.getByRole("checkbox", { name: /^10\.0\.0\.0\/8/ }));
    await user.click(screen.getByRole("button", { name: "Check answer" }));
    expect(screen.getByText("Not quite.")).toBeInTheDocument();
    // Only the chosen option is marked, so a wrong try does not give the answer away.
    expect(screen.queryByLabelText("Correct answer")).not.toBeNull();
    expect(screen.getByRole("checkbox", { name: /^192\.168\.0\.0\/16$/ })).toBeInTheDocument();
    await user.click(screen.getByRole("checkbox", { name: /^192\.168\.0\.0\/16/ }));
    await user.click(screen.getByRole("button", { name: "Check answer" }));
    expect(screen.getByText("Correct.")).toBeInTheDocument();
  });

  it("shows a flow node's detail when it is selected", async () => {
    const user = userEvent.setup();
    renderWithProviders(<VisualBlock type="flow" payload={load("flow.valid.json")[1]} />);
    const node = await screen.findByRole("button", { name: "Real?" });
    await user.click(node);
    expect(node).toHaveAttribute("aria-pressed", "true");
    expect(screen.getByText("Check the logs.", { selector: "[aria-live] *" })).toBeInTheDocument();
  });

  it("updates chmod both ways", async () => {
    const user = userEvent.setup();
    renderWithProviders(<VisualBlock type="calculator" payload={{ tool: "chmod", initial: "754" }} />);
    expect(screen.getByText("rwxr-xr--")).toBeInTheDocument();
    await user.click(screen.getByRole("checkbox", { name: "Others: Write" }));
    expect(screen.getByText("rwxr-xrw-")).toBeInTheDocument();
    expect(screen.getByText(/Anyone on the system can change this file/)).toBeInTheDocument();
  });

  it("never sends the practice password anywhere and hides it by default", () => {
    renderWithProviders(<VisualBlock type="calculator" payload={{ tool: "password-entropy" }} />);
    const input = screen.getByLabelText("Example password");
    expect(input).toHaveAttribute("type", "password");
    expect(input).toHaveAttribute("autocomplete", "new-password");
    expect(input.closest("form")).toBeNull();
  });
});

describe("legacy diagrams", () => {
  it("draws the old OSI and TCP diagram data through the generic components", () => {
    renderWithProviders(
      <Diagram
        diagramKey="osi-model"
        data={{
          layers: [
            { number: 7, name: "Application", unit: "Data", role: "Apps.", protocols: ["HTTP"], attack: "XSS." },
          ],
        }}
      />,
    );
    expect(screen.getByRole("tab", { name: /Application/ })).toBeInTheDocument();
    expect(screen.getByText("Attack at this layer")).toBeInTheDocument();
  });

  it("draws a legacy handshake as a sequence", () => {
    renderWithProviders(
      <Diagram
        diagramKey="tcp-handshake"
        data={{
          participants: { client: "Client", server: "Server" },
          steps: [
            {
              from: "server",
              label: "SYN-ACK",
              fields: "ack=1",
              explanation: "Agrees.",
              states: { client: "A", server: "B" },
            },
          ],
        }}
      />,
    );
    expect(screen.getByRole("img", { name: "Server to Client: SYN-ACK ack=1" })).toBeInTheDocument();
  });
});
