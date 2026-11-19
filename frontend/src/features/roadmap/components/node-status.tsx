import { Bookmark, Check, Circle, Lock } from "lucide-react";
import { cn } from "@/lib/utils";

export type NodeStatus = "locked" | "available" | "in_progress" | "completed";

const styles: Record<NodeStatus, string> = {
  locked: "border-dashed border-rule bg-paper-3 text-muted",
  available: "border-rule-2 bg-paper-2 text-ink",
  in_progress: "border-accent bg-accent-soft text-ink",
  completed: "border-accent bg-accent text-accent-ink",
};

function ProgressRing({ percent }: { percent: number }) {
  const r = 6.5;
  const c = 2 * Math.PI * r;
  return (
    <svg viewBox="0 0 16 16" aria-hidden className="size-4 -rotate-90">
      <circle cx="8" cy="8" r={r} fill="none" stroke="var(--color-rule-2)" strokeWidth="2" />
      <circle
        cx="8"
        cy="8"
        r={r}
        fill="none"
        stroke="var(--color-accent)"
        strokeWidth="2"
        strokeDasharray={`${(percent / 100) * c} ${c}`}
      />
    </svg>
  );
}

type RoadmapNodeProps = {
  title: string;
  status: NodeStatus;
  statusLabel: string;
  /** 0 to 100, shown for in-progress nodes. */
  percent?: number;
  /** Recommended as review (the learner already scored well in this domain). */
  review?: { label: string };
  className?: string;
};

/** The visual of one roadmap topic node (docs/DESIGN_SYSTEM.md §6). Status is icon plus text, never colour alone. */
export function RoadmapNodeCard({ title, status, statusLabel, percent = 0, review, className }: RoadmapNodeProps) {
  return (
    <div
      className={cn(
        "flex min-w-0 flex-col gap-2 rounded-card border px-4 py-3",
        styles[status],
        review && status !== "completed" && "border-attn border-solid",
        className,
      )}
    >
      <span className="font-medium leading-snug">{title}</span>
      <span className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
        <span className="inline-flex items-center gap-1.5">
          {status === "locked" && <Lock aria-hidden className="size-4" />}
          {status === "available" && <Circle aria-hidden className="size-4" />}
          {status === "in_progress" && <ProgressRing percent={percent} />}
          {status === "completed" && <Check aria-hidden className="size-4" />}
          {statusLabel}
        </span>
        {status === "in_progress" ? <span className="measured">{percent}%</span> : null}
        {review ? (
          <span className="inline-flex items-center gap-1 rounded-chip border border-attn bg-attn-soft px-1.5 text-ink">
            <Bookmark aria-hidden className="size-3.5" />
            {review.label}
          </span>
        ) : null}
      </span>
    </div>
  );
}
