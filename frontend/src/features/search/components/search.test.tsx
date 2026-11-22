import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import { Highlight, SearchDialog } from "./search-dialog";

const mocks = { search: vi.fn() };
vi.mock("../api", async (original) => ({ ...(await original<typeof import("../api")>()), search: (q: string) => mocks.search(q) }));

beforeAll(() => {
  // jsdom has no <dialog> behaviour; open and close it the way the browser would.
  HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) {
    this.setAttribute("open", "");
  };
  HTMLDialogElement.prototype.close ??= function (this: HTMLDialogElement) {
    this.removeAttribute("open");
    this.dispatchEvent(new Event("close"));
  };
});

const results = {
  query: "net",
  courses: [{ slug: "networking-fundamentals", title: "Networking Fundamentals", summary: "Packets.", domain: "Networking" }],
  lessons: [
    { slug: "intro-to-computer-networks", title: "Introduction to computer networks", summary: null, course: "Networking Fundamentals" },
    { slug: "osi-model", title: "The OSI model", summary: "Seven layers of the network.", course: "Networking Fundamentals" },
  ],
  careers: [],
  posts: [],
};

describe("search", () => {
  beforeEach(() => mocks.search.mockReset());

  it("opens from the button, waits for two characters and groups the results", async () => {
    mocks.search.mockResolvedValue(results);
    const user = userEvent.setup();
    renderWithProviders(<SearchDialog />);

    await user.click(screen.getByRole("button", { name: "Search" }));
    const box = screen.getByRole("searchbox", { name: "Search courses, lessons, careers and posts" });
    expect(box).toHaveFocus();
    await user.type(box, "n");
    expect(screen.getByText("Type at least 2 characters.")).toBeInTheDocument();
    expect(mocks.search).not.toHaveBeenCalled();

    await user.type(box, "et");
    expect(await screen.findByText("3 results")).toBeInTheDocument();
    expect(mocks.search).toHaveBeenCalledTimes(1);
    expect(mocks.search).toHaveBeenCalledWith("net");

    const lessons = screen.getByRole("region", { name: "Lessons" });
    expect(within(lessons).getAllByRole("link")).toHaveLength(2);
    expect(within(lessons).getByRole("link", { name: /The OSI model/ })).toHaveAttribute("href", "/learn/osi-model");
    expect(within(screen.getByRole("region", { name: "Courses" })).getByRole("link")).toHaveAttribute("href", "/courses/networking-fundamentals");
    expect(screen.queryByRole("region", { name: "Careers" })).not.toBeInTheDocument();
  });

  it("opens with / or Ctrl+K, but not while typing elsewhere", async () => {
    const user = userEvent.setup();
    renderWithProviders(
      <>
        <input aria-label="Elsewhere" />
        <SearchDialog />
      </>,
    );

    await user.click(screen.getByRole("textbox", { name: "Elsewhere" }));
    await user.keyboard("/");
    expect(screen.queryByRole("searchbox")).not.toBeInTheDocument();

    await user.keyboard("{Control>}k{/Control}");
    expect(await screen.findByRole("searchbox")).toBeInTheDocument();
  });

  it("says when nothing matches and when search fails", async () => {
    mocks.search.mockResolvedValueOnce({ query: "zz", courses: [], lessons: [], careers: [], posts: [] }).mockRejectedValueOnce(new Error("down"));
    const user = userEvent.setup();
    renderWithProviders(<SearchDialog />);

    await user.click(screen.getByRole("button", { name: "Search" }));
    await user.type(screen.getByRole("searchbox"), "zz");
    expect(await screen.findByText("Nothing matches “zz”. Try a shorter or different word.")).toBeInTheDocument();

    await user.type(screen.getByRole("searchbox"), "q");
    expect(await screen.findByText("Search isn't available right now. Try again in a moment.")).toBeInTheDocument();
  });

  it("closes on one Escape, even with text in the box", async () => {
    mocks.search.mockResolvedValue(results);
    const user = userEvent.setup();
    renderWithProviders(<SearchDialog />);

    await user.click(screen.getByRole("button", { name: "Search" }));
    await user.type(screen.getByRole("searchbox"), "net{Escape}");
    await waitFor(() => expect(screen.queryByRole("searchbox")).not.toBeInTheDocument());
  });

  it("returns focus to where it was when Escape closes a search opened by shortcut", async () => {
    mocks.search.mockResolvedValue(results);
    const user = userEvent.setup();
    renderWithProviders(
      <>
        <button type="button">Courses</button>
        <SearchDialog />
      </>,
    );

    const link = screen.getByRole("button", { name: "Courses" });
    link.focus();
    await user.keyboard("{Control>}k{/Control}");
    await user.type(await screen.findByRole("searchbox"), "net{Escape}");
    await waitFor(() => expect(screen.queryByRole("searchbox")).not.toBeInTheDocument());
    expect(link).toHaveFocus();
  });

  it("closes when a result is chosen", async () => {
    mocks.search.mockResolvedValue(results);
    const user = userEvent.setup();
    renderWithProviders(<SearchDialog />);

    await user.click(screen.getByRole("button", { name: "Search" }));
    await user.type(screen.getByRole("searchbox"), "net");
    const link = await screen.findByRole("link", { name: /The OSI model/ });
    link.addEventListener("click", (e) => e.preventDefault());
    await user.click(link);
    await waitFor(() => expect(screen.queryByRole("searchbox")).not.toBeInTheDocument());
  });

  it("marks the matching part of a title, ignoring case, as plain text", () => {
    const { container } = render(<Highlight text="Introduction to <b>networks</b>" term="NET" />);
    expect(container.querySelector("mark")?.textContent).toBe("net");
    expect(container.querySelector("b")).toBeNull();
  });
});
