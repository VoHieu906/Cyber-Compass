import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { PageShell } from "@/components/layout/page-shell";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { AdminNav } from "@/features/admin/components/admin-nav";
import { RequireAdmin } from "@/features/admin/components/require-admin";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("admin");
  // The console is never indexed, whoever finds the URL.
  return { title: { template: `%s · ${t("title")}`, default: t("title") }, robots: { index: false, follow: false } };
}

/**
 * Admin console shell: its own route group, so admin code is split from learner pages.
 * The amber rule marks a privileged area (amber = attention, docs/DESIGN_SYSTEM.md).
 * Authorization is enforced by the API; RequireAdmin only shapes the interface.
 */
export default async function AdminLayout({ children }: LayoutProps<"/">) {
  const t = await getTranslations("admin");
  return (
    <PageShell>
      <RequireAuth>
        <RequireAdmin>
          <div className="border-t-2 border-attn">
            <div className="container-page flex flex-col gap-8 py-[var(--space-xl)]">
              <p className="flex items-center gap-2 text-sm font-medium text-ink-2">
                <span aria-hidden className="size-1.5 rounded-full bg-attn" />
                {t("title")}
              </p>
              <div className="grid gap-8 lg:grid-cols-[minmax(0,12rem)_minmax(0,1fr)] lg:gap-[var(--gap-columns)]">
                <AdminNav />
                <div className="min-w-0">{children}</div>
              </div>
            </div>
          </div>
        </RequireAdmin>
      </RequireAuth>
    </PageShell>
  );
}
