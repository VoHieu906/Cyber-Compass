import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { describe, expect, it } from "vitest";
import type { Engine } from "@/features/labs/types";
import { renderWithProviders } from "@/test/render";
import type { LabDefinition } from "../../types";
import { CheckpointsPanel } from "../checkpoints-panel";
import { ScenarioPanel } from "../scenario-panel";

const FIXTURES = resolve(__dirname, "../../../../../../backend/app/Modules/Labs/schemas/fixtures");
const starter = (engine: Engine) =>
  JSON.parse(readFileSync(`${FIXTURES}/${engine}/starter.json`, "utf8")) as {
    scenario: LabDefinition["scenario"];
    solutions: LabDefinition["solutions"];
  };

function definition(engine: Engine): LabDefinition {
  const s = starter(engine);
  return {
    title: "T",
    summary: "S",
    briefing: "B",
    domain: "linux",
    lesson: null,
    engine,
    difficulty: 1,
    estimated_minutes: 5,
    scenario: s.scenario,
    solutions: s.solutions,
  };
}

/** The scenario and checkpoints panels over one definition, as the editor holds it. */
function Harness({
  engine,
  tab = "scenario",
  spy,
}: {
  engine: Engine;
  tab?: "scenario" | "checkpoints";
  spy?: (d: LabDefinition) => void;
}) {
  const [def, setDef] = useState(definition(engine));
  const update = (next: LabDefinition) => {
    setDef(next);
    spy?.(next);
  };
  return tab === "scenario" ? (
    <ScenarioPanel
      def={def}
      errors={{}}
      onChange={(scenario) => update({ ...def, scenario })}
      onValidity={() => undefined}
    />
  ) : (
    <CheckpointsPanel
      def={def}
      kinds={["answer", "traffic"]}
      errors={{}}
      onChange={(checkpoints) => update({ ...def, scenario: { ...def.scenario, checkpoints } })}
      onValidity={() => undefined}
    />
  );
}

describe("the shell lab's form", () => {
  it("edits the machine and its files, keeping their order", async () => {
    let last: LabDefinition | undefined;
    const user = userEvent.setup();
    renderWithProviders(<Harness engine="linux-shell" spy={(d) => (last = d)} />);

    const host = screen.getByLabelText("Host name");
    await user.clear(host);
    await user.type(host, "web-02");
    expect(last?.scenario.hostname).toBe("web-02");

    await user.click(screen.getByRole("button", { name: "Add a file" }));
    expect(Object.keys(last!.scenario.files as object)).toEqual([
      "/home/learner/notes.txt",
      "/etc/hostname",
      "/home/learner/file-1.txt",
    ]);
    // The new file is the one being edited: rename it and write its text.
    const path = screen.getByLabelText("Path");
    await user.clear(path);
    await user.type(path, "/var/log/new.log");
    await user.type(screen.getByLabelText("Content"), "line one");
    expect(last!.scenario.files).toMatchObject({ "/var/log/new.log": "line one", "/etc/hostname": "lab-01\n" });
    expect(Object.keys(last!.scenario.files as object).at(-1)).toBe("/var/log/new.log");

    await user.click(screen.getByRole("button", { name: "Remove this file" }));
    expect(Object.keys(last!.scenario.files as object)).toEqual(["/home/learner/notes.txt", "/etc/hostname"]);
  });

  it("will not remove the last file", async () => {
    const user = userEvent.setup();
    const one = definition("linux-shell");
    one.scenario.files = { "/etc/hostname": "lab-01\n" };
    renderWithProviders(
      <ScenarioPanel def={one} errors={{}} onChange={() => undefined} onValidity={() => undefined} />,
    );

    expect(screen.getByRole("button", { name: "Remove this file" })).toBeDisabled();
    await user.click(screen.getByRole("button", { name: "Add a file" }));
  });

  it("shows the schema mirror's problem beside the field", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Harness engine="linux-shell" />);

    await user.clear(screen.getByLabelText("Host name"));
    await user.type(screen.getByLabelText("Host name"), "Web 02");
    expect(
      await screen.findByText(/Use lowercase letters, digits and hyphens, starting with a letter/),
    ).toBeInTheDocument();
  });

  it("switches to the JSON of the same scenario and back without losing a change", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Harness engine="linux-shell" />);

    await user.clear(screen.getByLabelText("Host name"));
    await user.type(screen.getByLabelText("Host name"), "web-02");
    await user.click(screen.getByRole("radio", { name: "JSON" }));
    const json = screen.getByLabelText(/Scenario \(JSON/) as HTMLTextAreaElement;
    expect(json.value).toContain('"hostname": "web-02"');

    // Edited in JSON, shown in the form.
    await user.clear(json);
    await user.click(json);
    await user.paste(
      json.value ||
        '{"hostname":"web-03","user":"learner","home":"/home/learner","cwd":"/home/learner","files":{"/etc/hostname":"x\\n","/home/learner/a":"a"}}',
    );
    await user.click(screen.getByRole("radio", { name: "Form" }));
    expect(screen.getByLabelText("Host name")).toHaveValue("web-03");
  });

  it("holds the switch while the JSON does not parse", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Harness engine="linux-shell" />);

    await user.click(screen.getByRole("radio", { name: "JSON" }));
    const json = screen.getByLabelText(/Scenario \(JSON/);
    await user.click(json);
    await user.paste("{ not json");
    expect(screen.getByRole("radio", { name: "Form" })).toBeDisabled();
    expect(screen.getByText(/Fix the JSON first/)).toBeInTheDocument();
  });
});

describe("the firewall's form", () => {
  it("edits the rules as a table, in order", async () => {
    let last: LabDefinition | undefined;
    const user = userEvent.setup();
    renderWithProviders(<Harness engine="firewall-rules" spy={(d) => (last = d)} />);

    await user.click(screen.getByRole("button", { name: "Add a rule" }));
    expect(last!.scenario.rules).toEqual([
      { action: "allow", proto: "tcp", port: 80, source: "any" },
      { action: "allow", proto: "tcp", port: 80, source: "any" },
    ]);

    const second = screen.getAllByRole("listitem").filter((li) => within(li).queryByLabelText("Port"))[1];
    await user.selectOptions(within(second).getByLabelText("Action"), "deny");
    const port = within(second).getByLabelText(/^Port/);
    await user.clear(port);
    expect((last!.scenario.rules as { port?: number }[])[1]).not.toHaveProperty("port");
    await user.type(port, "4a43");
    expect(last!.scenario.rules).toMatchObject([{}, { action: "deny", port: 443 }]);

    await user.click(screen.getByRole("button", { name: "Move rule 2 up" }));
    expect(last!.scenario.rules).toMatchObject([
      { action: "deny", port: 443 },
      { action: "allow", port: 80 },
    ]);
    await user.click(screen.getByRole("button", { name: "Remove rule 1" }));
    expect(last!.scenario.rules).toEqual([{ action: "allow", proto: "tcp", port: 80, source: "any" }]);
  });

  it("changes the default policy and the server", async () => {
    let last: LabDefinition | undefined;
    const user = userEvent.setup();
    renderWithProviders(<Harness engine="firewall-rules" spy={(d) => (last = d)} />);

    await user.selectOptions(screen.getByLabelText("Default policy"), "deny");
    expect(last!.scenario.default).toBe("deny");
    await user.clear(screen.getByLabelText("Server name"));
    await user.type(screen.getByLabelText("Server name"), "db-01");
    expect(last!.scenario.server).toEqual({ name: "db-01", ip: "10.0.20.10" });
  });

  it("shows a problem at a rule's own field", async () => {
    const user = userEvent.setup();
    renderWithProviders(<Harness engine="firewall-rules" />);

    const port = screen.getAllByLabelText(/^Port/)[0];
    await user.clear(port);
    await user.type(port, "70000");
    expect(await screen.findByText(/Too big|<=65535|65535/)).toBeInTheDocument();
  });
});

describe("the traffic checkpoint's packets", () => {
  it("edits the test packets as a table and shows the same data as JSON", async () => {
    let last: LabDefinition | undefined;
    const user = userEvent.setup();
    renderWithProviders(<Harness engine="firewall-rules" tab="checkpoints" spy={(d) => (last = d)} />);

    await user.click(screen.getByRole("button", { name: "Add a packet" }));
    const packets = (last!.scenario.checkpoints[0].packets as unknown[]) ?? [];
    expect(packets).toHaveLength(3);

    await user.selectOptions(screen.getByLabelText("Expected verdict of packet 3"), "deny");
    expect((last!.scenario.checkpoints[0].packets as { expect: string }[])[2].expect).toBe("deny");

    await user.click(screen.getByRole("radio", { name: "JSON" }));
    expect((screen.getByLabelText(/Fields of checkpoint 1/) as HTMLTextAreaElement).value).toContain(
      '"expect": "deny"',
    );
    await user.click(screen.getByRole("radio", { name: "Form" }));
    expect(screen.getByLabelText("Expected verdict of packet 3")).toHaveValue("deny");
  });
});

describe("an engine without a form", () => {
  it("stays on the JSON editor with no switch", () => {
    renderWithProviders(<Harness engine="http-inspector" />);

    expect(screen.getByLabelText(/Scenario \(JSON/)).toBeInTheDocument();
    expect(screen.queryByRole("radio", { name: "Form" })).not.toBeInTheDocument();
  });
});
