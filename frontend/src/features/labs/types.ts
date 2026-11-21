/**
 * Shapes of the labs API (docs/API_SPECIFICATION.md#labs, ARCHITECTURE §7). The scenario is
 * only what the learner may see; answers, hidden packets and routes stay on the server.
 */

export type Engine = "linux-shell" | "firewall-rules" | "http-inspector";

export type LabProgress = { started: boolean; completed: boolean; passed: number; total: number };

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

export type Checkpoint = { id: string; title: string; kind: string; hint: string | null; passed: boolean };

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

export type LabAttempt<S = unknown> = { id: number; version: number; state: S; completed_at: string | null };

export type LabDetail = LabSummary & {
  briefing: string;
  scenario: ShellScenario | FirewallScenario | HttpScenario;
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
  | { type: "send"; request: HttpRequest };

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
  };
  newly_passed: string[];
  checkpoints: Checkpoint[];
  attempt: LabAttempt;
};
