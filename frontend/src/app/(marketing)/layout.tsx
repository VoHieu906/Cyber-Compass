import { PageShell } from "@/components/layout/page-shell";

export default function MarketingLayout({ children }: LayoutProps<"/">) {
  return <PageShell>{children}</PageShell>;
}
