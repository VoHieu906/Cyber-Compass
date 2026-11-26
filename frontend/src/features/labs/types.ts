/**
 * Shapes of the labs API (docs/API_SPECIFICATION.md#labs, ARCHITECTURE §7). The scenario is
 * only what the learner may see; answers, hidden packets and routes stay on the server.
 */

export type Engine = "linux-shell" | "firewall-rules" | "http-inspector" | "evidence-review" | "incident-response";

/** `passed` and `total` count tasks only; `holds` counts conditions already true at the start (to keep, not earn). */
export type LabProgress = { started: boolean; completed: boolean; passed: number; total: number; holds: number };

export type LabSummary = {
  slug: string;
  title: string;
  summary: string;
  engine: Engine;
  difficulty: 1 | 2 | 3;
  estimated_minutes: number;
  domain: { slug: string; name: string };
  lesson: { slug: string; title: string } | null;
  progress: LabProgress;
};

/** A `conclusion` checkpoint also carries its question, the options (already in this attempt's own order) and what can be cited now. */
export type Checkpoint = {
  id: string;
  title: string;
  kind: string;
  hint: string | null;
  passed: boolean;
  starts_true: boolean;
  question?: string;
  options?: { id: string; text: string }[];
  max_cited?: number;
  citable?: { id: string; label: string }[];
};

export type ShellEntry = { command: string; output: string; error: boolean };
export type ShellScenario = { hostname: string; user: string; commands: string[] };
export type ShellState = { cwd: string; prompt: string; history: ShellEntry[] };

export type FirewallRule = { action: "allow" | "deny"; proto: "tcp" | "udp" | "any"; port: number | null; source: string };
export type Packet = { proto: "tcp" | "udp"; port: number; source: string };
export type PacketTest = Packet & { verdict: "allow" | "deny"; rule: number | null };
export type FirewallScenario = { server: { name: string; ip: string }; max_rules: number };
export type FirewallState = { rules: FirewallRule[]; default: "allow" | "deny"; tests: PacketTest[] };

export type HttpMessage = { status: number; headers: Record<string, string>; body: string };
export type HttpRequest = { method: string; path: string; headers?: Record<string, string>; cookies?: Record<string, string>; body?: string };
export type HttpScenario = {
  app: { name: string; origin: string };
  session: { name: string; value: string };
  captured: { request: HttpRequest; response: HttpMessage }[];
  methods: string[];
};
export type HttpState = { sent: { request: HttpRequest; status: number }[] };

/** Evidence review: artifacts of plain text lines. `max_marks` is the most lines one artifact may hold marked. */
export type EvidenceScenario = {
  artifacts: {
    id: string;
    title: string;
    max_marks: number;
    views: { id: string; label: string; lines: string[]; mono: boolean }[];
  }[];
};
/** Marks are `artifact:view:line` keys (line from 1). */
export type EvidenceState = { marks: string[] };

/** Incident response: the scenario shows only the variables; everything else arrives through the view as the learner plays. */
export type IncidentScenario = { variables: { id: string; label: string; values: { id: string; label: string }[] }[] };
export type IncidentChange = { label: string; from: string; to: string };
export type IncidentState = {
  situation: string;
  ended: boolean;
  vars: { id: string; label: string; value: string; value_label: string }[];
  choices: { id: string; label: string; available: boolean }[];
  timeline: { decision: string; consequence: string; attacker: string; changes: IncidentChange[] }[];
  /** Tasks no sequence of choices can pass from here, said only when the lab can no longer be completed. */
  dead_end: { unreachable: string[] } | null;
};

type DebriefLine = { artifact: string; view: string; line: number; text: string };
/** What a learner may read once the lab is complete (D80). Never present during play. */
export type Debrief = {
  indicators?: { id: string; title: string; explanation: string; required: boolean; found: boolean; lines: DebriefLine[] }[];
  wrong?: DebriefLine[];
  /** Incident response: the learner's path, each decision with its trade-off and where another choice would have led. */
  path?: { decision: string; tradeoff: string; alternatives: { label: string; tradeoff: string; keeps_open: boolean }[] }[];
  final?: { label: string; value: string }[];
  /** `tries` counts the submissions up to the right one. */
  conclusions: { checkpoint: string; title: string; explanation: string | null; tries: number | null }[];
};

export type LabAttempt<S = unknown> = {
  id: number;
  version: number;
  state: S;
  completed_at: string | null;
  /** The scenario changed since the learner began, so the lab started over (D75). */
  restarted: boolean;
  debrief: Debrief | null;
};

export type LabDetail = LabSummary & {
  briefing: string;
  scenario: ShellScenario | FirewallScenario | HttpScenario | EvidenceScenario | IncidentScenario;
  checkpoints: Checkpoint[];
  attempt: LabAttempt | null;
};

export type LabAction =
  | { type: "command"; command: string }
  | { type: "answer"; checkpoint: string; value: string }
  | { type: "add_rule"; rule: FirewallRule; position?: number }
  | { type: "remove_rule"; index: number }
  | { type: "move_rule"; from: number; to: number }
  | { type: "set_default"; policy: "allow" | "deny" }
  | { type: "test"; packet: Packet }
  | { type: "send"; request: HttpRequest }
  | { type: "mark"; artifact: string; view: string; line: number; on: boolean }
  | { type: "conclude"; checkpoint: string; option: string; cited: string[] }
  | { type: "choose"; choice: string };

export type LabActionResult = {
  output: {
    text?: string;
    error?: boolean;
    clear?: boolean;
    verdict?: "allow" | "deny";
    rule?: number | null;
    response?: HttpMessage;
    checkpoint?: string;
    correct?: boolean;
    consequence?: string;
    attacker?: string;
    changes?: IncidentChange[];
  };
  newly_passed: string[];
  checkpoints: Checkpoint[];
  attempt: LabAttempt;
};
