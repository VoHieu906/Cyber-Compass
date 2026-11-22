/** Blog API shapes (backend app/Modules/Blog/Http/Resources, F2). */

import type { Paginated } from "@/features/catalog/types";
import type { LessonSection } from "@/features/learning/types";

export type Cover = { id: string; url: string; alt: string; width: number | null; height: number | null };

export type PostSummary = {
  slug: string;
  title: string;
  summary: string;
  tags: string[];
  estimated_minutes: number;
  published_at: string | null;
  author: string | null;
  cover: Cover | null;
};

/** A post for the reader: its sections are the lesson blocks, serialized the same way. */
export type Post = PostSummary & { updated_at: string | null; sections: LessonSection[] };

/** GET /posts: a page of cards plus every tag in use, for the filter. */
export type PostPage = Paginated<PostSummary> & { tags: string[] };
