import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { parseBlocks, Prose } from "./prose";

describe("parseBlocks", () => {
  it("splits paragraphs on blank lines and joins wrapped lines", () => {
    expect(parseBlocks("One line\ncontinues.\n\nSecond paragraph.")).toEqual([
      { kind: "paragraph", text: "One line continues." },
      { kind: "paragraph", text: "Second paragraph." },
    ]);
  });

  it("recognises bullet lists", () => {
    expect(parseBlocks("- first\n- second")).toEqual([{ kind: "list", items: ["first", "second"] }]);
  });

  it("recognises numbered lists and keeps their starting number", () => {
    expect(parseBlocks("3. third\n4) fourth")).toEqual([{ kind: "ordered", start: 3, items: ["third", "fourth"] }]);
  });

  it("treats a block with mixed lines as a paragraph", () => {
    expect(parseBlocks("Intro:\n- not a list")).toEqual([{ kind: "paragraph", text: "Intro: - not a list" }]);
  });
});

describe("Prose", () => {
  it("renders inline code and bold as elements", () => {
    const { container } = render(<Prose text={"Run `ls -la` to see **hidden** files."} />);

    expect(container.querySelector("code")).toHaveTextContent("ls -la");
    expect(container.querySelector("strong")).toHaveTextContent("hidden");
  });

  it("never turns content into HTML", () => {
    const { container } = render(<Prose text={'<img src=x onerror="alert(1)"> and <script>alert(2)</script>'} />);

    expect(container.querySelector("img")).toBeNull();
    expect(container.querySelector("script")).toBeNull();
    expect(screen.getByText(/<img src=x/)).toBeInTheDocument();
  });

  it("does not interpret markup inside code", () => {
    const { container } = render(<Prose text={"`<b>**not bold**</b>`"} />);

    expect(container.querySelector("strong")).toBeNull();
    expect(container.querySelector("code")).toHaveTextContent("<b>**not bold**</b>");
  });

  it("renders https links that open safely in a new tab", () => {
    render(<Prose text={"Read [RFC 9293](https://www.rfc-editor.org/rfc/rfc9293) first."} />);

    const link = screen.getByRole("link", { name: "RFC 9293" });
    expect(link).toHaveAttribute("href", "https://www.rfc-editor.org/rfc/rfc9293");
    expect(link).toHaveAttribute("target", "_blank");
    expect(link.getAttribute("rel")).toContain("noopener");
  });

  it("keeps the text of any other link but never makes it clickable", () => {
    const { container } = render(
      <Prose text={"[a](javascript:alert(1)) [b](http://example.com) [c](/admin) [d](https://)"} />,
    );

    expect(container.querySelector("a")).toBeNull();
    expect(container).toHaveTextContent("a");
  });
});
