import Link from "next/link";
import { cn } from "@/lib/utils";

/** The compass needle: a rotated square split into a filled and an outlined half. */
function Mark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden className={cn("size-6", className)}>
      <rect x="3.5" y="3.5" width="17" height="17" rx="3" fill="none" stroke="var(--color-rule-2)" strokeWidth="1.5" />
      <path d="M12 5.5 15 12h-6z" fill="var(--color-accent)" />
      <path d="M12 18.5 9 12h6z" fill="none" stroke="var(--color-ink)" strokeWidth="1.25" strokeLinejoin="round" />
    </svg>
  );
}

export function Wordmark({ label, href = "/" }: { label: string; href?: string }) {
  return (
    <Link href={href} className="inline-flex items-center gap-2 rounded-control text-ink">
      <Mark />
      <span className="max-[29.99rem]:sr-only font-display text-[1.0625rem] font-medium tracking-[-0.02em]">{label}</span>
    </Link>
  );
}
