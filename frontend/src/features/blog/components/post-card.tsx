import { Newspaper } from "lucide-react";
import Link from "next/link";
import type { PostSummary } from "../types";
import { PostMeta, TagLinks } from "./post-meta";

/** A blog card. The title is the link (its ::after covers the card); tags sit above it as their own links. */
export function PostCard({ post }: { post: PostSummary }) {
  return (
    <article className="relative flex h-full flex-col bg-paper-2">
      {post.cover ? (
        // eslint-disable-next-line @next/next/no-img-element -- re-encoded WebP from the API's upload storage
        <img
          src={post.cover.url}
          alt=""
          width={post.cover.width ?? undefined}
          height={post.cover.height ?? undefined}
          loading="lazy"
          className="aspect-[5/2] w-full border-b border-rule bg-paper-3 object-cover"
        />
      ) : (
        <div
          aria-hidden
          className="flex aspect-[5/2] w-full items-center justify-end border-b border-rule bg-paper-3 px-6"
        >
          <Newspaper className="size-10 text-ink-2" />
        </div>
      )}
      <div className="flex flex-1 flex-col gap-3 p-5">
        <PostMeta post={post} />
        <h2 className="text-[length:var(--text-lg)] leading-snug">
          <Link
            href={`/blog/${post.slug}`}
            className="rounded-chip after:absolute after:inset-0 after:content-[''] focus-visible:outline-offset-4"
          >
            {post.title}
          </Link>
        </h2>
        <p className="line-clamp-3 text-sm">{post.summary}</p>
        <TagLinks tags={post.tags} className="mt-auto pt-1" />
      </div>
    </article>
  );
}
