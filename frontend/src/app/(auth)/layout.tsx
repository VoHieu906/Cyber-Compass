import { Suspense } from "react";
import { PageShell } from "@/components/layout/page-shell";
import { GuestOnly } from "@/features/auth/components/guest-only";

export default function AuthLayout({ children }: LayoutProps<"/">) {
  return (
    <PageShell>
      <div className="container-page py-[var(--space-2xl)] sm:py-[var(--space-3xl)]">
        <Suspense>
          <GuestOnly>{children}</GuestOnly>
        </Suspense>
      </div>
    </PageShell>
  );
}
