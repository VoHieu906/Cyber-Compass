import type { ReactNode } from "react";
import { Compass } from "@/features/compass/compass";
import { CompassPageProvider } from "@/features/compass/page-items";
import { SiteFooter } from "./site-footer";
import { SiteHeader } from "./site-header";

/** Header, main landmark, footer and the compass (D51), shared by the marketing, auth and app route groups. */
export function PageShell({ children }: { children: ReactNode }) {
  return (
    <CompassPageProvider>
      <SiteHeader />
      <main id="main" className="flex-1">
        {children}
      </main>
      <SiteFooter />
      {/* Outside the header: its backdrop filter would make it the box a fixed child is placed in. */}
      <Compass />
    </CompassPageProvider>
  );
}
