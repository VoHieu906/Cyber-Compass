import { CircleAlert, CircleCheck, Info } from "lucide-react";
import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

type Tone = "error" | "success" | "info";

const tones: Record<Tone, { box: string; icon: string; Icon: typeof Info }> = {
  error: { box: "border-[color-mix(in_oklab,var(--color-bad)_45%,var(--color-rule))] bg-bad-soft", icon: "text-bad", Icon: CircleAlert },
  success: { box: "border-[color-mix(in_oklab,var(--color-ok)_45%,var(--color-rule))] bg-ok-soft", icon: "text-ok", Icon: CircleCheck },
  info: { box: "border-rule bg-paper-3", icon: "text-muted", Icon: Info },
};

/** A form- or page-level message. Errors interrupt (role=alert); the rest are announced politely. */
export function Alert({ tone, children, className }: { tone: Tone; children: ReactNode; className?: string }) {
  const { box, icon, Icon } = tones[tone];
  return (
    <div
      role={tone === "error" ? "alert" : "status"}
      className={cn("flex items-start gap-2.5 rounded-control border px-3.5 py-3 text-sm text-ink", box, className)}
    >
      <Icon aria-hidden className={cn("mt-0.5 size-4 shrink-0", icon)} />
      <div className="min-w-0">{children}</div>
    </div>
  );
}
