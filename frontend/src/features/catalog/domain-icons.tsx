import { BookOpen, Code2, Cpu, Globe, KeyRound, Network, Server, ShieldCheck, Terminal, type LucideIcon } from "lucide-react";

/** Presentation only: which glyph stands for a domain. Unknown domains get a neutral book. */
const icons: Record<string, LucideIcon> = {
  networking: Network,
  "operating-systems": Cpu,
  linux: Terminal,
  programming: Code2,
  "web-security": Globe,
  cryptography: KeyRound,
  "system-administration": Server,
  "security-fundamentals": ShieldCheck,
};

export function DomainIcon({ slug, className }: { slug: string; className?: string }) {
  const Icon = icons[slug] ?? BookOpen;
  return <Icon aria-hidden className={className} strokeWidth={1.5} />;
}
