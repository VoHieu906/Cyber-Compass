import type { ComponentType } from "react";
import type { Engine } from "@/features/labs/types";
import type { PathMessages, Scenario } from "../types";

/**
 * A form for an engine's scenario (D77). The shell owns everything else; an engine with no entry
 * here edits its scenario as validated JSON, so a new engine works the day it is registered.
 * Part C adds the forms of the three first engines.
 */
export type EngineFormProps = {
  scenario: Scenario;
  errors: PathMessages;
  onChange: (scenario: Scenario) => void;
};

export const ENGINE_FORMS: Partial<Record<Engine, ComponentType<EngineFormProps>>> = {};
