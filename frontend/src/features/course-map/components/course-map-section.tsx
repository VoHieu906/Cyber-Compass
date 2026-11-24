import { useTranslations } from "next-intl";
import type { CourseMapData } from "../types";
import { CourseMap } from "./course-map";
import { CourseMapOutline } from "./outline";

/**
 * The course page's map section (F3). Nothing at all when the course has no map. From `md` the
 * drawn map with its text version underneath; below `md` the outline is the view (D25).
 */
export function CourseMapSection({
  map,
  courseSlug,
  courseTitle,
}: {
  map: CourseMapData | null;
  courseSlug: string;
  courseTitle: string;
}) {
  const t = useTranslations("courseMap");
  if (!map || map.lessons.length === 0) return null;

  return (
    <section aria-labelledby="course-map-title" className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h2 id="course-map-title" tabIndex={-1} className="text-[length:var(--text-title)]">
          {t("title")}
        </h2>
        <p className="max-w-[68ch] text-ink-2">{t("lede")}</p>
      </div>
      <div className="max-md:hidden">
        <CourseMap map={map} courseSlug={courseSlug} courseTitle={courseTitle} />
      </div>
      <div className="md:hidden">
        <CourseMapOutline map={map} />
      </div>
      {/* The browser may open it before hydration (a click while the page loads, or find-in-page
          matching text inside): `open` is the reader's, React never sets it. Covers this element only. */}
      <details className="max-md:hidden" suppressHydrationWarning>
        <summary className="cursor-pointer text-sm text-ink underline decoration-rule-2 underline-offset-4">
          {t("textVersion")}
        </summary>
        <div className="pt-3">
          <CourseMapOutline map={map} />
        </div>
      </details>
    </section>
  );
}
