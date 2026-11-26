import type { Engine } from "@/features/labs/types";

/**
 * Shapes of the lab management API (docs/API_SPECIFICATION.md, F7, D73 to D79). Everything here
 * carries answers (the scenario and the solutions): it comes only from `admin/content/labs`, and
 * nothing in this feature logs it or sends it anywhere else.
 */

export type CheckpointDef = { id: string; title: string; kind: string; hint?: string } & Record<string, unknown>;
export type Scenario = { checkpoints: CheckpointDef[] } & Record<string, unknown>;
export type Solution = { name: string; actions: Record<string, unknown>[] };
export type LabSolutions = { solutions: Solution[]; decoys: Solution[] };

export type LabDefinition = {
  title: string;
  summary: string;
  briefing: string;
  domain: string;
  lesson: string | null;
  engine: Engine;
  difficulty: 1 | 2 | 3;
  estimated_minutes: number;
  scenario: Scenario;
  solutions: LabSolutions;
};

export type LabStatus = "draft" | "published";

export type LabRow = {
  id: number;
  slug: string;
  title: string;
  engine: Engine;
  status: LabStatus;
  is_archived: boolean;
  difficulty: number;
  domain: { slug: string; name: string };
  has_draft: boolean;
  /** The database owns the lab (D73): an admin edited it or made it. Otherwise a content file does. */
  owned: boolean;
  /** Also defined in labs.php while labs/<slug>.php exists: the old entry should be deleted. */
  shadowed: boolean;
  started: number;
  completed: number;
};

export type EngineInfo = { key: Engine; checkpoint_kinds: string[] };
export type DomainInfo = { slug: string; name: string };
export type LabsIndex = { data: LabRow[]; meta: { domains: DomainInfo[]; engines: EngineInfo[] } };

export type Impact = { breaking: boolean; in_progress: number; completed: number };

export type AdminLab = {
  id: number;
  slug: string;
  status: LabStatus;
  is_archived: boolean;
  position: number;
  has_draft: boolean;
  owned: boolean;
  /** 0 when the lab has no draft; sent back with every save and publish. */
  lock_version: number;
  scenario_version: number;
  definition: LabDefinition;
  impact: Impact;
  domains: DomainInfo[];
};

/** Messages by path in the definition, such as `scenario.checkpoints.0.path`. */
export type PathMessages = Record<string, string[]>;
export type CheckReport = { errors: PathMessages; warnings: PathMessages; valid: boolean };

export type LabRevision = { id: number; version: number; note: string | null; created_by: number | null; created_at: string };
