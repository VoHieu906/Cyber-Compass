import { useTranslations } from "next-intl";
import Link from "next/link";
import type { CourseMapData } from "../types";

/**
 * The course map as a nested list (F3): the big problem, each lesson's problem, its relations
 * and its architecture with links to the sections. The full text alternative of the drawn map,
 * and the map itself below `md` (the D25 rule). Renders on the server.
 */
export function CourseMapOutline({ map }: { map: CourseMapData }) {
  const t = useTranslations("courseMap");
  const titles = new Map(map.lessons.map((l) => [l.slug, l.title]));

  return (
    <div className="flex flex-col gap-4">
      <p>
        <span className="font-medium text-ink">{t("goal")}: </span>
        <span className="text-ink-2">{map.problem}</span>
      </p>
      <ol className="flex flex-col gap-4">
        {map.lessons.map((lesson) => {
          const relations = map.edges.filter((e) => e.from === lesson.slug);
          return (
            <li key={lesson.slug} className="flex flex-col gap-1.5 border-l-2 border-rule pl-3">
              <Link
                href={`/learn/${lesson.slug}`}
                className="min-h-6 self-start font-medium text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
              >
                {lesson.title}
              </Link>
              {lesson.problem ? <p className="text-ink-2">{lesson.problem}</p> : null}
              {relations.length ? (
                <div>
                  <p className="text-sm text-muted">{t("relations")}</p>
                  <ul className="list-disc pl-5 text-sm leading-6 text-ink-2">
                    {relations.map((e) => (
                      <li key={`${e.to}-${e.type}`}>
                        {t(`edges.${e.type}`)} {titles.get(e.to)}
                        {e.label ? ` (${e.label})` : ""}
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
              {lesson.shape ? (
                <div>
                  <p className="text-sm text-muted">
                    {t("builtAs", { shape: `${t(`shapes.${lesson.shape}`)}, ${t(`shapeHelp.${lesson.shape}`)}` })}
                  </p>
                  <ol className="list-decimal pl-5 text-sm leading-6 text-ink-2">
                    {lesson.nodes.map((node) => (
                      <li key={node.id}>
                        {/* A matrix part means little without its row and column. */}
                        {node.cell && lesson.rows && lesson.cols
                          ? `${lesson.rows[node.cell[0]]} · ${lesson.cols[node.cell[1]]}: `
                          : ""}
                        {node.section ? (
                          <Link
                            href={`/learn/${lesson.slug}#${node.section}`}
                            className="text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
                          >
                            {node.label}
                          </Link>
                        ) : (
                          <span className="text-ink">{node.label}</span>
                        )}
                        {node.note ? `: ${node.note}` : ""}
                        {node.lesson && titles.has(node.lesson)
                          ? ` (${t("goToLesson", { title: titles.get(node.lesson)! })})`
                          : ""}
                      </li>
                    ))}
                  </ol>
                </div>
              ) : null}
            </li>
          );
        })}
      </ol>
    </div>
  );
}
