import type { ComponentType } from "react";
import type { Engine } from "@/features/labs/types";
import type { CheckpointDef, PathMessages, Scenario } from "../types";
import { FirewallForm } from "./forms/firewall-form";
import { PacketsForm } from "./forms/packets-form";
import { ShellForm } from "./forms/shell-form";

/**
 * Forms for an engine's scenario (D77). The shell owns everything else; an engine with no entry here
 * edits its scenario as validated JSON, so a new engine works the day it is registered. A form edits
 * only `scenario`, shows the problems found at its paths (the zod mirror's and the server's), and
 * can always be swapped for the JSON view of the same data.
 */
export type EngineFormProps = {
  scenario: Scenario;
  /** Problems by path in the definition, such as `scenario.rules.0.port`. */
  errors: PathMessages;
  onChange: (scenario: Scenario) => void;
};

export const ENGINE_FORMS: Partial<Record<Engine, ComponentType<EngineFormProps>>> = {
  "linux-shell": ShellForm,
  "firewall-rules": FirewallForm,
  // The HTTP inspector stays on the JSON editor: its routes and captured exchanges are nested
  // messages, which read better as JSON than as a form.
};

/** A form for the fields of one checkpoint kind, beside the id, title and hint every kind has. */
export type CheckpointFormProps = {
  checkpoint: CheckpointDef;
  /** Path of this checkpoint in the definition, `scenario.checkpoints.2`. */
  path: string;
  errors: PathMessages;
  onChange: (checkpoint: CheckpointDef) => void;
};

export const CHECKPOINT_FORMS: Record<string, ComponentType<CheckpointFormProps>> = {
  traffic: PacketsForm,
};
