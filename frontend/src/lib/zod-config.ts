import { config } from "zod";

/*
 * Imported for its side effect by every module that uses zod at runtime (zod-config.test.ts checks).
 * The Content Security Policy forbids eval in production (D42). zod's fast parsers are compiled with
 * `new Function`, and its one-time probe for that is reported as a CSP violation even though the
 * error is caught; jitless parsing is fast enough for lesson blocks and forms. Only `config` is
 * imported here, so zod stays tree-shaken and out of pages that never parse anything.
 */
config({ jitless: true });
