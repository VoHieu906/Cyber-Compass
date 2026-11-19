import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api/client";
import { renderWithProviders } from "@/test/render";
import { LoginForm } from "./login-form";

const replace = vi.fn();
const refresh = vi.fn();
let searchParams = new URLSearchParams();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace, refresh, push: vi.fn() }),
  useSearchParams: () => searchParams,
  usePathname: () => "/login",
}));
vi.mock("next-themes", () => ({ useTheme: () => ({ setTheme: vi.fn(), theme: "system" }) }));
vi.mock("@/i18n/actions", () => ({ setLocale: vi.fn() }));

const loginMock = vi.fn();
vi.mock("../api", () => ({ login: (...args: unknown[]) => loginMock(...args) }));

const user = {
  id: 1,
  name: "Lan",
  email: "lan@example.com",
  role: "learner",
  email_verified_at: null,
  created_at: null,
  profile: { avatar_url: null, phone: null, date_of_birth: null, education_level: null, locale: "en", theme: "dark" },
};

async function fill(email: string, password: string) {
  await userEvent.type(screen.getByLabelText("Email"), email);
  await userEvent.type(screen.getByLabelText("Password"), password);
  await userEvent.click(screen.getByRole("button", { name: "Log in" }));
}

describe("LoginForm", () => {
  beforeEach(() => {
    replace.mockReset();
    loginMock.mockReset();
    searchParams = new URLSearchParams();
  });

  it("validates before calling the API", async () => {
    renderWithProviders(<LoginForm />);

    await fill("not-an-email", "x");

    expect(await screen.findByText("Enter a valid email address.")).toBeInTheDocument();
    expect(screen.getByLabelText("Email")).toHaveAttribute("aria-invalid", "true");
    expect(loginMock).not.toHaveBeenCalled();
  });

  it("signs in and goes to the requested page", async () => {
    searchParams = new URLSearchParams({ next: "/settings/security" });
    loginMock.mockResolvedValue(user);
    renderWithProviders(<LoginForm />);

    await fill("lan@example.com", "secure-pass-1");

    await waitFor(() => expect(replace).toHaveBeenCalledWith("/settings/security"));
    expect(loginMock.mock.calls[0][0]).toEqual({ email: "lan@example.com", password: "secure-pass-1", remember: false });
  });

  it("ignores an off-site next parameter", async () => {
    searchParams = new URLSearchParams({ next: "https://evil.example" });
    loginMock.mockResolvedValue(user);
    renderWithProviders(<LoginForm />);

    await fill("lan@example.com", "secure-pass-1");

    await waitFor(() => expect(replace).toHaveBeenCalledWith("/settings/profile"));
  });

  it("shows the server's message above the form when credentials are wrong", async () => {
    loginMock.mockRejectedValue(
      new ApiError(422, {
        message: "The given data was invalid.",
        code: "validation_failed",
        errors: { email: ["These credentials do not match our records."] },
      }),
    );
    renderWithProviders(<LoginForm />);

    await fill("lan@example.com", "wrong-pass-1");

    expect(await screen.findByRole("alert")).toHaveTextContent("These credentials do not match our records.");
    expect(replace).not.toHaveBeenCalled();
  });

  it("explains a network failure", async () => {
    loginMock.mockRejectedValue(new ApiError(0, { message: "Network error" }));
    renderWithProviders(<LoginForm />);

    await fill("lan@example.com", "secure-pass-1");

    expect(await screen.findByRole("alert")).toHaveTextContent("Can't reach the server.");
  });

  it("toggles password visibility", async () => {
    renderWithProviders(<LoginForm />);
    const input = screen.getByLabelText("Password");

    await userEvent.click(screen.getByRole("button", { name: "Show password" }));
    expect(input).toHaveAttribute("type", "text");

    await userEvent.click(screen.getByRole("button", { name: "Hide password" }));
    expect(input).toHaveAttribute("type", "password");
  });
});
