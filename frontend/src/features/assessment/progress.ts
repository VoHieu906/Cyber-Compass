import type { AttemptQuestion } from "./types";

/**
 * Pure helpers for the attempt flow. Scoring is not here: it happens on the server only
 * (ARCHITECTURE §1: the frontend holds no business rules).
 */

export type Selections = Record<number, number | null>;

export type DomainProgress = {
  slug: string;
  name: string;
  /** Index of the domain's first question in the attempt. */
  start: number;
  total: number;
  answered: number;
};

/** The learner's current choices: the server's saved answers overlaid with local ones. */
export function selectionsFrom(questions: AttemptQuestion[], local: Selections): Selections {
  return Object.fromEntries(questions.map((q) => [q.id, q.id in local ? local[q.id] : q.selected_option_id]));
}

export function answeredCount(questions: AttemptQuestion[], selections: Selections): number {
  return questions.filter((q) => selections[q.id] != null).length;
}

/** Where to resume: the first unanswered question, or the first question if all are answered. */
export function firstUnanswered(questions: AttemptQuestion[], selections: Selections): number {
  const index = questions.findIndex((q) => selections[q.id] == null);
  return index === -1 ? 0 : index;
}

/** Questions grouped by domain, in the order they are asked. */
export function domainProgress(questions: AttemptQuestion[], selections: Selections): DomainProgress[] {
  const groups: DomainProgress[] = [];
  questions.forEach((q, i) => {
    let group = groups.at(-1);
    if (!group || group.slug !== q.domain.slug) {
      group = { slug: q.domain.slug, name: q.domain.name, start: i, total: 0, answered: 0 };
      groups.push(group);
    }
    group.total++;
    if (selections[q.id] != null) group.answered++;
  });
  return groups;
}

export function percent(part: number, whole: number): number {
  return whole > 0 ? Math.round((part / whole) * 100) : 0;
}
