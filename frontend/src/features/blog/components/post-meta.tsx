import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { Duration } from "@/features/catalog/components/labels";
import { cn } from "@/lib/utils";
import type { PostSummary } from "../types";

/** Date and reading time, in the measured face. */
export function PostMeta({
  post,
  className,
}: {
  post: Pick<PostSummary, "published_at" | "estimated_minutes">;
  className?: string;
}) {
  const t = useTranslations("blog");
  const format = useFormatter();
  return (
    <p className={cn("flex flex-wrap items-center gap-x-4 gap-y-1 text-sm", className)}>
      {post.published_at ? (
        <time dateTime={post.published_at} className="measured text-ink">
          {format.dateTime(new Date(post.published_at), { dateStyle: "medium" })}
        </time>
      ) : null}
      <span>
        <span className="sr-only">{t("readingTime")}: </span>
        <Duration minutes={post.estimated_minutes} className="text-ink" /> {t("read")}
      </span>
    </p>
  );
}

/** Tags as filter links (`/blog?tag=`). Above a card's link layer, so each tag stays its own target. */
export function TagLinks({ tags, className }: { tags: string[]; className?: string }) {
  const t = useTranslations("blog");
  if (tags.length === 0) return null;
  return (
    <ul aria-label={t("tags")} className={cn("relative z-[1] flex flex-wrap gap-1.5", className)}>
      {tags.map((tag) => (
        <li key={tag}>
          <Link
            href={`/blog?tag=${encodeURIComponent(tag)}`}
            className="measured inline-flex rounded-chip border border-rule px-1.5 py-0.5 text-xs text-ink transition-[border-color] duration-[var(--dur-micro)] hover:border-muted"
          >
            #{tag}
          </Link>
        </li>
      ))}
    </ul>
  );
}
