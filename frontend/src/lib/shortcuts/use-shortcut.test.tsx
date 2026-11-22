import { render } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import type { Overrides } from "./registry";
import { ShortcutOverridesContext, useShortcut } from "./use-shortcut";

function Probe({ onSave, onSearch }: { onSave: () => void; onSearch: () => void }) {
  useShortcut("editor.save", onSave);
  useShortcut("search.open", onSearch);
  return <input aria-label="field" />;
}

function setup(overrides: Overrides = {}) {
  const onSave = vi.fn();
  const onSearch = vi.fn();
  const view = render(
    <ShortcutOverridesContext.Provider value={overrides}>
      <Probe onSave={onSave} onSearch={onSearch} />
    </ShortcutOverridesContext.Provider>,
  );
  return { onSave, onSearch, field: view.getByLabelText("field") };
}

describe("useShortcut", () => {
  it("saves on Ctrl+S and ⌘+S, also from inside a field, as the editor did", async () => {
    const user = userEvent.setup();
    const { onSave, field } = setup();
    await user.keyboard("{Control>}s{/Control}");
    await user.keyboard("{Meta>}s{/Meta}");
    field.focus();
    await user.keyboard("{Control>}s{/Control}");
    expect(onSave).toHaveBeenCalledTimes(3);
  });

  it("keeps plain keys quiet while typing", async () => {
    const user = userEvent.setup();
    const { onSearch, field } = setup();
    await user.keyboard("/");
    field.focus();
    await user.keyboard("/");
    expect(onSearch).toHaveBeenCalledTimes(1);
    expect(field).toHaveValue("/");
  });

  it("follows a rebound or disabled shortcut", async () => {
    const user = userEvent.setup();
    const { onSave, onSearch } = setup({ "editor.save": { code: "KeyE", alt: true, shift: true }, "search.open": null });
    await user.keyboard("{Control>}s{/Control}/");
    await user.keyboard("{Alt>}{Shift>}e{/Shift}{/Alt}");
    expect(onSave).toHaveBeenCalledTimes(1);
    expect(onSearch).not.toHaveBeenCalled();
  });
});
