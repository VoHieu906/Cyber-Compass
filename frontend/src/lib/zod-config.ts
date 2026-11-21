import { z } from "zod";

/*
 * The Content Security Policy forbids eval in production (D42). zod's fast parsers are compiled
 * with `new Function`, and its one-time probe for that is reported as a CSP violation even though
 * the error is caught. Jitless parsing is fast enough for lesson blocks and forms.
 */
z.config({ jitless: true });
