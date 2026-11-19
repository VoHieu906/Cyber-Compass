import { cn } from "@/lib/utils";
import { DomainIcon } from "../domain-icons";
import type { CourseSummary } from "../types";

/**
 * A thumbnail built from the course's own data: the domain glyph on a faint measuring
 * grid. Real artwork can replace it later through `thumbnail_url` without changing the card.
 */
export function CourseThumbnail({ course, className }: { course: Pick<CourseSummary, "domain" | "thumbnail_url" | "title">; className?: string }) {
  if (course.thumbnail_url) {
    return (
      // eslint-disable-next-line @next/next/no-img-element -- remote course artwork from the API's storage
      <img src={course.thumbnail_url} alt="" className={cn("aspect-[5/2] w-full object-cover", className)} />
    );
  }

  return (
    <div
      aria-hidden
      className={cn(
        "flex aspect-[5/2] w-full items-center justify-end bg-paper-3 px-6",
        "[--grid:color-mix(in_oklab,var(--color-rule)_55%,transparent)]",
        "[background-image:linear-gradient(var(--grid)_1px,transparent_1px),linear-gradient(90deg,var(--grid)_1px,transparent_1px)] [background-size:20px_20px] [background-position:-1px_-1px]",
        className,
      )}
    >
      <DomainIcon slug={course.domain.slug} className="size-11 text-ink-2" />
    </div>
  );
}
