"use client";

import { Plus } from "lucide-react";
import { useEffect, useRef, useState } from "react";

type Row = { question: string; answer: string };

/** Questions as rows, using native <details>. Each answer is rendered only while its row is open. */
export function DisclosureRows({ rows }: { rows: Row[] }) {
  const [open, setOpen] = useState<Record<number, boolean>>({});
  const rowEls = useRef<(HTMLDetailsElement | null)[]>([]);
  // A row the browser opened before hydration (a click while loading, find-in-page) missed its toggle event.
  useEffect(() => {
    if (rowEls.current.some((d) => d?.open)) setOpen(Object.fromEntries(rowEls.current.map((d, i) => [i, !!d?.open])));
  }, []);

  return (
    <div className="border-t border-rule">
      {rows.map((row, i) => (
        <details
          key={row.question}
          ref={(el) => void (rowEls.current[i] = el)}
          // `open` is the reader's, set by the browser even before hydration.
          suppressHydrationWarning
          className="group border-b border-rule"
          onToggle={(e) => {
            const isOpen = e.currentTarget.open;
            setOpen((prev) => ({ ...prev, [i]: isOpen }));
          }}
        >
          <summary className="flex cursor-pointer list-none items-center justify-between gap-4 py-5 text-[length:var(--text-lg)] font-medium text-ink [&::-webkit-details-marker]:hidden">
            {row.question}
            <Plus
              aria-hidden
              className="size-5 shrink-0 text-muted transition-transform duration-[var(--dur-short)] ease-out group-open:rotate-45"
            />
          </summary>
          {open[i] ? <p className="max-w-[60ch] pb-5 text-ink-2">{row.answer}</p> : null}
        </details>
      ))}
    </div>
  );
}
