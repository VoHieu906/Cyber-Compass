import type { Page } from "@playwright/test";
import { expect } from "./test";

const API_URL = process.env.E2E_API_URL ?? "http://localhost:8000";

type Section = { type: string; heading: string | null; payload: Record<string, unknown> };

/** A long post (F2): `count` blocks, a heading on every other one, with a callout and a graph in between. */
export function longPostSections(count = 60): Section[] {
  return Array.from({ length: count }, (_, i): Section => {
    if (i % 20 === 10) {
      return {
        type: "flow",
        heading: `Build, ship, run ${i}`,
        payload: {
          nodes: [
            { id: "build", label: "Build" },
            { id: "ship", label: "Ship" },
            { id: "run", label: "Run" },
          ],
          edges: [
            { from: "build", to: "ship" },
            { from: "ship", to: "run" },
          ],
        },
      };
    }
    if (i % 20 === 5)
      return { type: "callout", heading: null, payload: { text: "Never mount the Docker socket.", tone: "warning" } };
    return {
      type: "prose",
      heading: i % 2 === 0 ? `Part ${i + 1}` : null,
      payload: {
        text: `Containers share the host kernel. ${"Paragraph text about isolation and images. ".repeat(12)}`,
      },
    };
  });
}

/**
 * Creates and publishes a post through the admin API as the signed-in admin page (its session and
 * XSRF cookie), the calls the editor makes. Posts made by e2e accounts are removed by the purge.
 */
export async function publishPost(
  page: Page,
  post: { slug: string; title: string; summary: string; tags: string[]; sections: Section[] },
) {
  const call = (method: string, path: string, body?: unknown) =>
    page.evaluate(
      async ({ method, url, body }) => {
        const xsrf = decodeURIComponent(
          document.cookie
            .split("; ")
            .find((c) => c.startsWith("XSRF-TOKEN="))
            ?.split("=")[1] ?? "",
        );
        const response = await fetch(url, {
          method,
          credentials: "include",
          headers: { Accept: "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": xsrf },
          body: body === undefined ? undefined : JSON.stringify(body),
        });
        return {
          status: response.status,
          json: (await response.json().catch(() => null)) as { data: Record<string, unknown> } | null,
        };
      },
      { method, url: `${API_URL}/api/v1${path}`, body },
    );

  const created = await call("POST", "/admin/content/posts", { title: post.title, slug: post.slug });
  expect(created.status, "create post").toBe(201);
  const doc = created.json!.data as { id: number; lock_version: number; working_copy: Record<string, unknown> };
  const saved = await call("PUT", `/admin/content/posts/${doc.id}/draft`, {
    ...doc.working_copy,
    summary: post.summary,
    tags: post.tags,
    sections: post.sections,
    lock_version: doc.lock_version,
  });
  expect(saved.status, "save draft").toBe(200);
  const published = await call("POST", `/admin/content/posts/${doc.id}/publish`, {
    lock_version: (saved.json!.data as { lock_version: number }).lock_version,
  });
  expect(published.status, "publish").toBe(200);
  return doc.id;
}
