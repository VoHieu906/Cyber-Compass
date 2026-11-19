import { ChevronRight } from "lucide-react";
import Link from "next/link";

type Item = { label: string; href?: string };

/** The last item is the current page and is not a link. */
export function Breadcrumb({ items, label }: { items: Item[]; label: string }) {
  return (
    <nav aria-label={label}>
      <ol className="flex flex-wrap items-center gap-1.5 text-sm">
        {items.map((item, i) => {
          const last = i === items.length - 1;
          return (
            <li key={`${item.label}-${i}`} className="flex min-w-0 items-center gap-1.5">
              {item.href && !last ? (
                <Link href={item.href} className="rounded-chip text-muted underline-offset-4 hover:text-ink hover:underline">
                  {item.label}
                </Link>
              ) : (
                <span aria-current={last ? "page" : undefined} className="truncate text-ink">
                  {item.label}
                </span>
              )}
              {!last ? <ChevronRight aria-hidden className="size-3.5 shrink-0 text-muted" /> : null}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
