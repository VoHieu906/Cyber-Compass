import { account } from "./support/accounts";
import { longPostSections, publishPost } from "./support/blog";
import { expect, signIn, test } from "./support/test";

/**
 * The blog (F2): guests read posts on the shared lesson blocks, and admins write them with the
 * lesson editor. Posts made here belong to the e2e admin and are removed by the purge.
 */
test.describe("blog", () => {
  test("a guest reads a long post and moves around it with the table of contents", async ({ browser, page }) => {
    const slug = `e2e-long-post-${Date.now()}`;
    const admin = await browser.newPage();
    await signIn(admin, account("admin"));
    await publishPost(admin, {
      slug,
      title: "E2E long read about containers",
      summary: "Sixty blocks about containers.",
      tags: ["e2e-reading"],
      sections: longPostSections(60),
    });
    await admin.close();

    await page.goto("/blog?tag=e2e-reading");
    await page.getByRole("link", { name: "E2E long read about containers" }).click();
    await expect(page).toHaveURL(`/blog/${slug}`);
    await expect(page.getByRole("heading", { level: 1, name: "E2E long read about containers" })).toBeVisible();

    const toc = page.getByRole("navigation", { name: "On this page" });
    await expect(toc.getByRole("link")).toHaveCount(30);
    await toc.getByRole("link", { name: "Part 41", exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/blog/${slug}#`));
    await expect(toc.locator('[aria-current="location"]')).toHaveText("Part 41");
    await expect(page.getByRole("heading", { level: 2, name: "Part 41" })).toBeInViewport();

    // The graph further down mounts as it comes near, without leaving its placeholder behind.
    await page.getByRole("heading", { level: 2, name: "Build, ship, run 50" }).scrollIntoViewIfNeeded();
    const graph = page.locator("section", { has: page.getByRole("heading", { name: "Build, ship, run 50" }) });
    await expect(graph.locator("svg").first()).toBeVisible();

    // Heading links: the address of a section.
    await expect(page.getByRole("button", { name: "Copy a link to “Part 41”" })).toBeAttached();
  });

  test("an admin drafts, previews and publishes a post; it appears on the blog and in search", async ({ page }) => {
    const title = `E2E post ${Date.now()}`;
    await signIn(page, account("admin"));
    await page.goto("/admin/posts");
    await page.getByRole("button", { name: "New post" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Title").fill(title);
    await dialog.getByRole("button", { name: "Create and edit" }).click();
    await expect(page.getByRole("heading", { level: 2, name: "Post details" })).toBeVisible();

    await page.getByLabel("Summary").fill("Written by the end-to-end suite.");
    await page.getByRole("button", { name: /^Text/ }).first().click();
    await page.getByLabel("Heading").fill("Why containers");
    await page.getByLabel("Text", { exact: true }).fill("Containers share one kernel.");
    await page.getByRole("button", { name: "Save draft" }).click();
    await expect(page.getByText("Draft saved.")).toBeVisible();

    await page.getByRole("tab", { name: "Preview" }).click();
    await expect(page.getByRole("heading", { level: 2, name: "Why containers" })).toBeVisible();

    await page.getByRole("button", { name: "Publish" }).click();
    await page
      .getByRole("dialog")
      .getByLabel(/Note for the history/)
      .fill("First version");
    await page.getByRole("dialog").getByRole("button", { name: "Publish" }).click();
    await expect(page.getByText("Published. Readers now see this version.")).toBeVisible();

    await page.goto("/blog");
    await expect(page.getByRole("link", { name: title })).toBeVisible();

    await page.getByRole("button", { name: /^Search/ }).click();
    await page.getByRole("searchbox").fill(title);
    const results = page.getByRole("dialog");
    await expect(results.getByRole("heading", { name: "Blog posts" })).toBeVisible();
    await results.getByRole("link", { name: new RegExp(title) }).click();
    await expect(page.getByRole("heading", { level: 1, name: title })).toBeVisible();
  });
});
