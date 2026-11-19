import { describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api/client";
import { applyServerErrors } from "./forms";

describe("applyServerErrors", () => {
  it("puts each server message on its field", () => {
    const setError = vi.fn();
    const error = new ApiError(422, {
      message: "The given data was invalid.",
      code: "validation_failed",
      errors: { email: ["The email has already been taken."], password: ["Too short.", "No number."] },
    });

    expect(applyServerErrors(error, setError, ["email", "password"])).toBe(true);
    expect(setError).toHaveBeenCalledWith("email", { type: "server", message: "The email has already been taken." });
    expect(setError).toHaveBeenCalledWith("password", { type: "server", message: "Too short." });
  });

  it("reports when an error has no matching field", () => {
    const setError = vi.fn();
    const error = new ApiError(422, { message: "x", code: "validation_failed", errors: { token: ["Invalid token."] } });

    expect(applyServerErrors(error, setError, ["password"])).toBe(false);
    expect(setError).not.toHaveBeenCalled();
  });

  it("leaves non-validation failures to the caller", () => {
    expect(applyServerErrors(new ApiError(500, {}), vi.fn(), ["email"])).toBe(false);
    expect(applyServerErrors(new Error("boom"), vi.fn(), ["email"])).toBe(false);
  });
});
