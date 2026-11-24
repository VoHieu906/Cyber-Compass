import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { renderWithProviders } from "@/test/render";
import type { PackageReport, Transfer } from "../types";

const api = vi.hoisted(() => ({
  checkPackage: vi.fn(),
  fetchTransfer: vi.fn(),
  applyTransfer: vi.fn(),
  validateAgain: vi.fn(),
}));
vi.mock("../api", () => api);
const upload = vi.hoisted(() => ({ uploadPackage: vi.fn() }));
vi.mock("../upload", () => upload);

import { ImportDialog } from "./import-dialog";

const report = (over: Partial<PackageReport> = {}): PackageReport => ({
  kind: "lesson-sections",
  generator: "Claude",
  valid: true,
  errors: {},
  warnings: [],
  documents: [
    {
      type: "lesson",
      slug: "osi-model",
      title: "The OSI model",
      id: 9,
      is_new: false,
      chapter: { id: 1, title: "How networks are built" },
      lock_version: 0,
      fingerprint: "abc",
      changes: [
        { change: "kept", key: "why", uuid: "u1", type: "prose", heading: "Why" },
        { change: "added", key: "devices-by-layer", uuid: "u2", type: "comparison", heading: "Devices by layer" },
      ],
    },
  ],
  ...over,
});
const transfer = (status: Transfer["status"], r: PackageReport | null = report()): Transfer => ({
  id: 3,
  direction: "import",
  status,
  file_name: "p.ccpkg.json",
  size_bytes: 10,
  report: r,
  error: null,
  expires_at: "",
  created_at: null,
});

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

afterEach(() => vi.clearAllMocks());

/** The dialog's one status line for screen readers (it outlives every step). */
const liveRegion = () => document.querySelector('p.sr-only[role="status"][aria-live="polite"]');

describe("ImportDialog", () => {
  it("checks pasted JSON and copies the errors as text for the chatbot", async () => {
    const user = userEvent.setup();
    const writeText = vi.spyOn(navigator.clipboard, "writeText").mockResolvedValue();
    api.checkPackage.mockResolvedValue(
      report({ valid: false, errors: { "operations.0.key": ['No section has the key "x".'] }, documents: [] }),
    );
    renderWithProviders(<ImportDialog open onClose={() => {}} />);

    await user.type(screen.getByLabelText("Or paste a JSON package to check it"), "{{}");
    await user.click(screen.getByRole("button", { name: "Check only" }));

    expect(await screen.findByText(/nothing can be applied/)).toBeInTheDocument();
    expect(screen.getByText("operations.0.key")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Copy errors for the chatbot" }));
    expect(writeText.mock.calls[0][0]).toContain('- operations.0.key: No section has the key "x".');
    expect(writeText.mock.calls[0][0]).toContain("keep every uuid and key unchanged");
    expect(screen.queryByRole("button", { name: "Apply to drafts" })).not.toBeInTheDocument();
    expect(liveRegion()).toHaveTextContent("The package has errors. Nothing was saved.");
  });

  it("uploads, shows the preview, applies and reports back once", async () => {
    const user = userEvent.setup();
    const onApplied = vi.fn();
    upload.uploadPackage.mockResolvedValue(transfer("validating", null));
    api.fetchTransfer.mockResolvedValueOnce(transfer("ready")).mockResolvedValue(transfer("applied"));
    api.applyTransfer.mockResolvedValue(transfer("applying"));
    renderWithProviders(<ImportDialog open onClose={() => {}} onApplied={onApplied} />);

    await user.upload(
      screen.getByLabelText("Package file"),
      new File(["{}"], "p.ccpkg.json", { type: "application/json" }),
    );
    await user.click(screen.getByRole("button", { name: "Upload and check" }));

    expect(await screen.findByText("Devices by layer")).toBeInTheDocument();
    await waitFor(() => expect(liveRegion()).toHaveTextContent("Checked. Review the changes below"));
    expect(screen.getByText("1 section unchanged")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Apply to drafts" }));

    expect(await screen.findByText(/Imported into drafts/, {}, { timeout: 4000 })).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "The OSI model" })).toHaveAttribute("href", "/admin/lessons/9");
    await waitFor(() => expect(onApplied).toHaveBeenCalledTimes(1));
    expect(liveRegion()).toHaveTextContent("Imported into drafts.");
  });

  it("keeps the actions closed while the editor has unsaved changes", () => {
    renderWithProviders(<ImportDialog open onClose={() => {}} blockedReason="Save or discard your changes first." />);

    expect(screen.getByText("Save or discard your changes first.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Upload and check" })).toBeDisabled();
  });
});
