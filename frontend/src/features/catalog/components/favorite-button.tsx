"use client";

import { Heart } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCurrentUser } from "@/features/auth/hooks";
import { cn } from "@/lib/utils";
import { useToggleFavorite } from "../hooks";

type Props = {
  slug: string;
  title: string;
  favorited: boolean;
  /** Show the word next to the heart (course page) or the heart alone (cards). */
  withLabel?: boolean;
  className?: string;
};

/** Favorite toggle. Guests get a link to log in and come back here instead of a dead button. */
export function FavoriteButton({ slug, title, favorited, withLabel = false, className }: Props) {
  const t = useTranslations("catalog");
  const tc = useTranslations("course");
  const { data: user } = useCurrentUser();
  const toggle = useToggleFavorite();
  const pathname = usePathname();

  const base = cn(
    "inline-flex items-center justify-center gap-2 rounded-control border text-sm font-medium",
    "transition-[background-color,border-color,color] duration-[var(--dur-micro)] ease-out",
    withLabel ? "h-10 px-3.5" : "size-9",
    className,
  );

  if (!user) {
    return (
      <Link
        href={`/login?next=${encodeURIComponent(pathname)}`}
        aria-label={withLabel ? undefined : t("favoriteSignIn")}
        title={t("favoriteSignIn")}
        className={cn(base, "border-rule-2 bg-paper-2 text-muted hover:text-ink")}
      >
        <Heart aria-hidden className="size-4" />
        {withLabel ? tc("favorite") : null}
      </Link>
    );
  }

  return (
    <button
      type="button"
      aria-pressed={favorited}
      aria-label={withLabel ? undefined : favorited ? t("unfavorite", { title }) : t("favorite", { title })}
      onClick={() => toggle.mutate({ slug, favorited: !favorited })}
      className={cn(
        base,
        favorited
          ? "border-accent bg-accent-soft text-ink"
          : "border-rule-2 bg-paper-2 text-muted hover:border-[color-mix(in_oklab,var(--color-rule-2)_60%,var(--color-ink))] hover:text-ink",
      )}
    >
      <Heart aria-hidden className={cn("size-4", favorited && "fill-accent text-accent")} />
      {withLabel ? (favorited ? tc("favorited") : tc("favorite")) : null}
    </button>
  );
}
