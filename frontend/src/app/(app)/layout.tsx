import { PageShell } from "@/components/layout/page-shell";
import { RequireAuth } from "@/features/auth/components/require-auth";
import { TimezoneSync } from "@/features/progress/components/timezone";

/** Signed-in area. Pages here render only once /me confirms a session. */
export default function AppLayout({ children }: LayoutProps<"/">) {
  return (
    <PageShell>
      <RequireAuth>
        <TimezoneSync />
        {children}
      </RequireAuth>
    </PageShell>
  );
}
