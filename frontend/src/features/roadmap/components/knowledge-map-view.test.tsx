import { screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { KnowledgeMap } from "../types";
import { KnowledgeMapView } from "./knowledge-map-view";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }), usePathname: () => "/roadmap/map" }));
const fetchKnowledgeMap = vi.fn();
vi.mock("../api", () => ({ fetchKnowledgeMap: () => fetchKnowledgeMap() }));

const lesson = (slug: string, status: "not_started" | "started" | "completed") => ({
  slug,
  title: `Lesson ${slug}`,
  summary: `About ${slug}.`,
  estimated_minutes: 15,
  is_required: true,
  status,
});

const map: KnowledgeMap = {
  domains: [
    { slug: "networking", name: "Networking", score: 82, band: "strength", completion: { completed: 1, total: 2, percent: 50 } },
    { slug: "cryptography", name: "Cryptography", score: null, band: null, completion: { completed: 0, total: 1, percent: 0 } },
    { slug: "programming", name: "Programming", score: null, band: null, completion: { completed: 0, total: 0, percent: 0 } },
  ],
  topics: [
    { slug: "networking-basics", title: "Networking basics", summary: "", level: 1, domain: "networking", status: "in_progress", percent: 50, in_path: true, requires: [], lessons: [lesson("a", "completed"), lesson("b", "not_started")] },
    { slug: "crypto", title: "Crypto building blocks", summary: "", level: 2, domain: "cryptography", status: "locked", percent: 0, in_path: false, requires: ["networking-basics"], lessons: [lesson("c", "not_started")] },
  ],
  edges: [{ from: "networking-basics", to: "crypto" }],
};

describe("KnowledgeMapView", () => {
  beforeEach(() => fetchKnowledgeMap.mockReset().mockResolvedValue(map));

  it("lists domains with their score and completion, and topics with status on phones", async () => {
    renderWithProviders(<KnowledgeMapView />);

    expect(await screen.findByRole("heading", { name: "Networking" })).toBeInTheDocument();
    expect(screen.getByText("82% in the assessment")).toBeInTheDocument();
    expect(screen.getByText("1 of 2 lessons completed")).toBeInTheDocument();
    expect(screen.getAllByRole("link", { name: "Not assessed yet" })[0]).toHaveAttribute("href", "/assessment");
    expect(screen.getByText("No lessons in this domain yet")).toBeInTheDocument();
    expect(screen.getByText("On your roadmap")).toBeInTheDocument();
    expect(screen.getByText("Needs: Networking basics")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Knowledge map" })).toHaveAttribute("aria-current", "page");
  });

  describe("on wide screens", () => {
    beforeEach(() => {
      vi.stubGlobal("matchMedia", (query: string) => ({ matches: query.includes("min-width"), addEventListener: vi.fn(), removeEventListener: vi.fn() }));
      HTMLDialogElement.prototype.showModal = function (this: HTMLDialogElement) {
        this.setAttribute("open", "");
      };
    });
    afterEach(() => vi.unstubAllGlobals());

    it("draws domains as rows and depth as columns, with expandable topics", async () => {
      renderWithProviders(<KnowledgeMapView />);

      const graph = await screen.findByRole("group", { name: "Knowledge map graph" });
      expect(within(graph).getByText("Foundation")).toBeInTheDocument();
      expect(within(graph).getByText("Working knowledge")).toBeInTheDocument();
      const crypto = within(graph).getByRole("button", { name: /Crypto building blocks/ });
      expect(crypto).toHaveAccessibleDescription(/Locked\. Cryptography\. Working knowledge\. Needs: Networking basics/);

      const basics = within(graph).getByRole("button", { name: /Networking basics/ });
      expect(basics).toHaveAttribute("aria-expanded", "false");
      await userEvent.click(basics);
      await userEvent.click(within(graph).getByRole("button", { name: /Lesson b/ }));
      expect(within(await screen.findByRole("dialog", { name: "Lesson b" })).getByRole("link", { name: /Start lesson/ })).toHaveAttribute("href", "/learn/b");
    });
  });
});
