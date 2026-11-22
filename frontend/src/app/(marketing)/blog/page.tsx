import { ChevronLeft, ChevronRight } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { PostCard } from "@/features/blog/components/post-card";
import type { PostPage } from "@/features/blog/types";
import { serverGetBody } from "@/lib/api/server";
import { siteUrl } from "@/lib/site";
import { cn } from "@/lib/utils";

const TAG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

/** `tag` and `page` from the address, ignored when malformed (the API would reject them). */
async function readQuery(searchParams: PageProps<"/blog">["searchParams"]) {
  const params = await searchParams;
  const tag = typeof params.tag === "string" && TAG.test(params.tag) ? params.tag : null;
  const page = typeof params.page === "string" && /^[1-9]\d{0,3}$/.test(params.page) ? Number(params.page) : 1;
  return { tag, page };
}

function href(tag: string | null, page = 1) {
  const query = new URLSearchParams();
  if (tag) query.set("tag", tag);
  if (page > 1) query.set("page", String(page));
  const qs = query.toString();
  return qs ? `/blog?${qs}` : "/blog";
}

export async function generateMetadata({ searchParams }: PageProps<"/blog">): Promise<Metadata> {
  const t = await getTranslations("blog");
  const { tag, page } = await readQuery(searchParams);
  return {
    metadataBase: siteUrl(),
    title: tag ? t("taggedTitle", { tag }) : t("title"),
    description: t("lede"),
    alternates: { canonical: href(tag, page) },
    openGraph: { type: "website", title: t("title"), description: t("lede"), url: href(tag, page) },
  };
}

/** The blog index, rendered on the server for search engines: newest first, a tag filter, link pagination. */
export default async function BlogPage({ searchParams }: PageProps<"/blog">) {
  const { tag, page } = await readQuery(searchParams);
  const query = new URLSearchParams({ page: String(page) });
  if (tag) query.set("tag", tag);
  const [t, posts] = await Promise.all([
    getTranslations("blog"), // Not cached: a post an admin just published should be on the blog at once.
    serverGetBody<PostPage>(`/api/v1/posts?${query}`, { revalidate: 0 }),
  ]);
  const { current_page: current, last_page: last } = posts.meta;

  return (
    <div className="container-page flex flex-col gap-8 py-[var(--space-2xl)]">
      <header className="flex max-w-[40rem] flex-col gap-3">
        <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
        <p className="text-[length:var(--text-lead)] text-ink-2">{t("lede")}</p>
      </header>

      {posts.tags.length > 0 ? (
        <nav aria-label={t("filterByTag")} className="flex flex-col gap-2">
          <p className="text-sm font-medium text-ink">{t("filterByTag")}</p>
          <ul className="flex flex-wrap gap-2">
            {[null, ...posts.tags].map((value) => {
              const active = value === tag;
              return (
                <li key={value ?? "all"}>
                  <Link
                    href={href(value)}
                    aria-current={active ? "page" : undefined}
                    className={cn(
                      "measured inline-flex rounded-chip border px-2 py-1 text-sm transition-[border-color,background-color] duration-[var(--dur-micro)]",
                      active ? "border-ink bg-ink text-paper" : "border-rule text-ink hover:border-muted",
                    )}
                  >
                    {value ? `#${value}` : t("allPosts")}
                  </Link>
                </li>
              );
            })}
          </ul>
        </nav>
      ) : null}

      {posts.data.length === 0 ? (
        <p className="panel p-6">{tag ? t("noTaggedPosts", { tag }) : t("noPosts")}</p>
      ) : (
        // Separate cards, not the joined `panels` grid: a young blog rarely fills a row.
        <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {posts.data.map((post) => (
            <li key={post.slug} className="panel overflow-hidden">
              <PostCard post={post} />
            </li>
          ))}
        </ul>
      )}

      {last > 1 ? (
        <nav aria-label={t("pagination")} className="flex items-center justify-between gap-4">
          {current > 1 ? (
            <Link
              href={href(tag, current - 1)}
              rel="prev"
              className={buttonVariants({ variant: "secondary", size: "sm" })}
            >
              <ChevronLeft aria-hidden />
              {t("newer")}
            </Link>
          ) : (
            <span />
          )}
          <p className="measured text-sm text-ink">{t("pageOf", { page: current, total: last })}</p>
          {current < last ? (
            <Link
              href={href(tag, current + 1)}
              rel="next"
              className={buttonVariants({ variant: "secondary", size: "sm" })}
            >
              {t("older")}
              <ChevronRight aria-hidden />
            </Link>
          ) : (
            <span />
          )}
        </nav>
      ) : null}
    </div>
  );
}
