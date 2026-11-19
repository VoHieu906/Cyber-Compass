import { getTranslations } from "next-intl/server";
import { cn } from "@/lib/utils";

/** Required level 1 to 3: three cells, filled up to the level, with the level named in words. */
export async function LevelMeter({ level, className }: { level: 1 | 2 | 3; className?: string }) {
  const t = await getTranslations("careers");
  const label = t(`level.${level}`);
  return (
    <span className={cn("inline-flex items-center gap-2", className)}>
      <span role="img" aria-label={t("levelMeter", { level, label })} className="inline-flex gap-0.5">
        {[1, 2, 3].map((n) => (
          <span key={n} className={cn("h-2 w-5 rounded-[1px]", n <= level ? "bg-accent" : "bg-rule")} />
        ))}
      </span>
      <span aria-hidden className="text-sm text-ink">
        {label}
      </span>
    </span>
  );
}
