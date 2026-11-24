import { randomBytes } from "node:crypto";
import { account } from "./support/accounts";
import { expect, signIn, test } from "./support/test";
import { png, zip } from "./support/zip";

/**
 * Content packages (F6): a real zip larger than one upload part goes through the browser, nginx,
 * PHP-FPM, the transfers disk and the real queue worker; then blocks are imported into the new
 * lesson's draft, published, read, and exported again. The lesson is named e2e-* and lives in a
 * course no other spec uses, so the purge removes it and parallel specs never see it.
 */
const COURSE = "System Hardening and Administration";
const CHAPTER = "Securing servers";

const envelope = (kind: string, body: object, target: object = {}) => ({
  format: "cybercompass.content",
  format_version: 1,
  kind,
  generator: "Playwright",
  target,
  ...body,
});

test.describe("content packages", () => {
  test.setTimeout(180_000);

  test("an admin imports a lesson zip and blocks, publishes them, and exports the lesson", async ({
    page,
    context,
  }) => {
    const slug = `e2e-pkg-${Date.now()}`;
    const title = `E2E package lesson ${slug.slice(-6)}`;
    await context.grantPermissions(["clipboard-read", "clipboard-write"]);
    await signIn(page, account("packagesAdmin"), "/admin/courses");
    await page.getByLabel("Course", { exact: true }).selectOption({ label: COURSE });
    // Choosing a course re-renders the page under ?course=; act only once its outline shows.
    await expect(page).toHaveURL(/\?course=\d+/);
    await expect(page.getByText("Hardening basics")).toBeVisible();

    // 1. A new lesson as a zip: a real image, long text through $file, and 9 MB of filler so the
    //    upload needs two 8 MB parts through the API (the local transfers disk).
    const lesson = envelope(
      "lesson",
      {
        document: {
          slug,
          title,
          minutes: 8,
          summary: "A lesson that arrived as a content package.",
          objectives: ["Read a lesson that came from a package"],
          takeaways: [],
          resources: [],
          sections: [
            {
              key: "why-packages",
              heading: "Why packages",
              type: "prose",
              payload: { text: { $file: "texts/why.md" } },
              sources: [{ label: "RFC 2119", url: "https://www.rfc-editor.org/rfc/rfc2119", kind: "rfc" }],
            },
            {
              key: "a-figure",
              heading: "A figure",
              type: "media",
              payload: { media: { file: "media/figure.png", alt: "A plain figure" }, caption: null },
            },
          ],
        },
      },
      { course: "system-hardening", chapter: CHAPTER },
    );
    const bundle = zip({
      "manifest.json": JSON.stringify(lesson),
      "texts/why.md": 'Packages carry **long text** with "quotes" and a \\ backslash, kept out of JSON escaping.',
      "media/figure.png": png(96, 64),
      "media/filler.png": randomBytes(9 * 1024 * 1024),
    });
    expect(bundle.length).toBeGreaterThan(8 * 1024 * 1024);

    await page.getByRole("button", { name: "Import lessons" }).click();
    let dialog = page.getByRole("dialog", { name: "Import a content package" });
    await dialog
      .getByLabel("Package file")
      .setInputFiles({ name: `${slug}.ccpkg.zip`, mimeType: "application/zip", buffer: bundle });
    await dialog.getByRole("button", { name: "Upload and check" }).click();

    await expect(dialog.getByText("New lesson (draft)")).toBeVisible({ timeout: 60_000 });
    await expect(dialog.getByText("Why packages")).toBeVisible();
    await expect(dialog.getByText(/joins no roadmap topic/)).toBeVisible();
    await dialog.getByRole("button", { name: "Apply to drafts" }).click();
    // The dialog's status line for screen readers announces it (and the visible alert says the same).
    await expect(dialog.locator('p.sr-only[role="status"]')).toHaveText(/Imported into drafts/, { timeout: 60_000 });
    await dialog.getByRole("link", { name: title }).click();
    await expect(page.getByRole("heading", { level: 1, name: title })).toBeVisible();

    // 2. A broken package pasted to check only: errors by place, copied for the chatbot.
    await page.getByRole("button", { name: "Import", exact: true }).click();
    dialog = page.getByRole("dialog", { name: "Import a content package" });
    await dialog
      .getByLabel("Or paste a JSON package to check it")
      .fill(
        JSON.stringify(
          envelope("lesson-sections", { operations: [{ op: "remove", key: "no-such-section" }] }, { lesson: slug }),
        ),
      );
    await dialog.getByRole("button", { name: "Check only" }).click();
    await expect(dialog.getByText("operations.0.key")).toBeVisible();
    await dialog.getByRole("button", { name: "Copy errors for the chatbot" }).click();
    const copied = await page.evaluate(() => navigator.clipboard.readText());
    expect(copied).toContain('CyberCompass content package (format "cybercompass.content", version 1): 1 error.');
    expect(copied).toContain("- operations.0.key: ");
    expect(copied).toContain("return the complete package as a single JSON document");
    await dialog.getByRole("button", { name: "Back" }).click();

    // 3. Blocks into the draft: a recap after the prose, uploaded as a .ccpkg.json file.
    const blocks = envelope(
      "lesson-sections",
      {
        operations: [
          {
            op: "insert_after",
            key: "why-packages",
            section: {
              key: "in-other-words",
              heading: "In other words",
              type: "callout",
              payload: {
                text: "A package is a parcel of lesson blocks that the admin unpacks into a draft.",
                tone: "recap",
              },
            },
          },
        ],
      },
      { lesson: slug },
    );
    await dialog.getByLabel("Package file").setInputFiles({
      name: "blocks.ccpkg.json",
      mimeType: "application/json",
      buffer: Buffer.from(JSON.stringify(blocks)),
    });
    await dialog.getByRole("button", { name: "Upload and check" }).click();
    await expect(dialog.getByText("Added")).toBeVisible({ timeout: 60_000 });
    await dialog.getByRole("button", { name: "Apply to drafts" }).click();
    // The dialog's status line for screen readers announces it (and the visible alert says the same).
    await expect(dialog.locator('p.sr-only[role="status"]')).toHaveText(/Imported into drafts/, { timeout: 60_000 });
    await dialog.getByRole("button", { name: "Close" }).click();
    await expect(page.getByText("3 blocks")).toBeVisible();

    // 4. Publish through the normal flow, then read it.
    await page.getByRole("button", { name: "Publish" }).click();
    await page.getByRole("dialog").getByRole("button", { name: "Publish" }).click();
    await expect(page.getByText("Published. Learners now see this version.")).toBeVisible();

    await page.goto(`/learn/${slug}`);
    await expect(page.getByRole("heading", { level: 1, name: title })).toBeVisible();
    await expect(page.getByText(/kept out of JSON escaping/)).toBeVisible();
    await expect(page.getByText("In other words")).toBeVisible();
    await page.getByText("Sources (1)").click();
    await expect(page.getByRole("link", { name: /RFC 2119/ })).toBeVisible();
    await expect(page.getByRole("img", { name: "A plain figure" })).toBeVisible();

    // 5. Export it again: JSON at once, a zip with its image from the worker.
    await page.goBack();
    await page.getByRole("button", { name: "Export", exact: true }).click();
    const exportDialog = page.getByRole("dialog", { name: "Export this lesson" });
    await expect(exportDialog.getByRole("link", { name: "Download JSON" })).toHaveAttribute(
      "href",
      /\/admin\/content\/lessons\/\d+\/package$/,
    );
    await exportDialog.getByLabel("Zip with images (.ccpkg.zip)").check();
    await exportDialog.getByRole("button", { name: "Prepare zip" }).click();
    await expect(exportDialog.getByText(/1 document and 1 image/)).toBeVisible({ timeout: 60_000 });
    const [download] = await Promise.all([
      page.waitForEvent("download"),
      exportDialog.getByRole("link", { name: /Download .*\.ccpkg\.zip/ }).click(),
    ]);
    const file = await download.createReadStream();
    const chunks: Buffer[] = [];
    for await (const chunk of file) chunks.push(chunk as Buffer);
    expect(Buffer.concat(chunks).subarray(0, 2).toString("latin1")).toBe("PK");
  });
});
