import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api/client";
import { renderWithProviders } from "@/test/render";
import { RegisterForm } from "./register-form";

const replace = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace, refresh: vi.fn(), push: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

const registerMock = vi.fn();
vi.mock("../api", () => ({ register: (...args: unknown[]) => registerMock(...args) }));

async function fill(values: { name?: string; email?: string; password?: string; confirm?: string }) {
  if (values.name) await userEvent.type(screen.getByLabelText("Full name"), values.name);
  if (values.email) await userEvent.type(screen.getByLabelText("Email"), values.email);
  if (values.password) await userEvent.type(screen.getByLabelText("Password"), values.password);
  if (values.confirm) await userEvent.type(screen.getByLabelText("Confirm password"), values.confirm);
  await userEvent.click(screen.getByRole("button", { name: "Create account" }));
}

describe("RegisterForm", () => {
  beforeEach(() => {
    replace.mockReset();
    registerMock.mockReset();
  });

  it("applies the same password rules as the API", async () => {
    renderWithProviders(<RegisterForm />);

    await fill({ name: "Lan", email: "lan@example.com", password: "onlyletters", confirm: "onlyletters" });

    expect(await screen.findByText("Include at least one number.")).toBeInTheDocument();
    expect(registerMock).not.toHaveBeenCalled();
  });

  it("catches a mismatched confirmation", async () => {
    renderWithProviders(<RegisterForm />);

    await fill({ name: "Lan", email: "lan@example.com", password: "secure-pass-1", confirm: "secure-pass-2" });

    expect(await screen.findByText("The passwords don't match.")).toBeInTheDocument();
  });

  it("shows a server error on the field it belongs to", async () => {
    registerMock.mockRejectedValue(
      new ApiError(422, {
        message: "The given data was invalid.",
        code: "validation_failed",
        errors: { email: ["The email has already been taken."] },
      }),
    );
    renderWithProviders(<RegisterForm />);

    await fill({ name: "Lan", email: "lan@example.com", password: "secure-pass-1", confirm: "secure-pass-1" });

    expect(await screen.findByText("The email has already been taken.")).toBeInTheDocument();
    expect(screen.getByLabelText("Email")).toHaveAttribute("aria-invalid", "true");
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  it("creates the account and continues", async () => {
    registerMock.mockResolvedValue({ id: 1 });
    renderWithProviders(<RegisterForm />);

    await fill({ name: "Lan", email: "lan@example.com", password: "secure-pass-1", confirm: "secure-pass-1" });

    await waitFor(() => expect(replace).toHaveBeenCalledWith("/dashboard"));
  });
});
