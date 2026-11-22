import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { PostReader } from "@/features/blog/components/post-reader";
import type { Post } from "@/features/blog/types";
import { serverGetOrNull } from "@/lib/api/server";
import { siteUrl } from "@/lib/site";

async function getPost(slug: string) {
  // Not cached, so a publish or an archive shows at once; generateMetadata and the page share one request.
  return serverGetOrNull<Post>(`/api/v1/posts/${encodeURIComponent(slug)}`, { revalidate: 0 });
}

export async function generateMetadata({ params }: PageProps<"/blog/[slug]">): Promise<Metadata> {
  const post = await getPost((await params).slug);
  if (!post) return {};
  const url = `/blog/${post.slug}`;
  return {
    metadataBase: siteUrl(),
    title: post.title,
    description: post.summary,
    alternates: { canonical: url },
    openGraph: {
      type: "article",
      url,
      title: post.title,
      description: post.summary,
      publishedTime: post.published_at ?? undefined,
      modifiedTime: post.updated_at ?? undefined,
      tags: post.tags,
      images: post.cover
        ? [
            {
              url: post.cover.url,
              alt: post.cover.alt,
              width: post.cover.width ?? undefined,
              height: post.cover.height ?? undefined,
            },
          ]
        : undefined,
    },
  };
}

/** A blog post, rendered on the server (its text is in the HTML for search engines) and readable by guests. */
export default async function BlogPostPage({ params }: PageProps<"/blog/[slug]">) {
  const post = await getPost((await params).slug);
  if (!post) notFound();
  return <PostReader post={post} />;
}
