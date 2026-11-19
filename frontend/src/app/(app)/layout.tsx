import { PageShell } from "@/components/layout/page-shell";
import { RequireAuth } from "@/features/auth/components/require-auth";

/** Signed-in area. Pages here render only once /me confirms a session. */
export default function AppLayout({ children }: LayoutProps<"/">) {
  return (
    <PageShell>
      <RequireAuth>{children}</RequireAuth>
    </PageShell>
  );
}
