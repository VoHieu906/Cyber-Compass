// Builds the sandboxed 3D runtime (docs/SANDBOXED_3D.md) into public/sandbox/scene3d/frame.js.
// Two passes with Vite: the worker (three.js + scene builder) is bundled first and embedded
// as a string in the frame script, which starts it from a blob URL inside the sandbox.
import { fileURLToPath } from "node:url";
import { build } from "vite";

const shared = {
  configFile: false,
  logLevel: "warn",
  // The same "@/" alias as the app (tsconfig paths), e.g. for @/lib/zod.
  resolve: { alias: { "@": fileURLToPath(new URL("../src", import.meta.url)) } },
  define: { "process.env.NODE_ENV": JSON.stringify("production") },
};

const worker = await build({
  ...shared,
  build: {
    write: false,
    minify: true,
    target: "es2022",
    lib: {
      entry: "src/sandbox/scene3d/worker.ts",
      formats: ["iife"],
      name: "SceneWorker",
      fileName: () => "worker.js",
    },
  },
});
const workerCode = (Array.isArray(worker) ? worker[0] : worker).output[0].code;

await build({
  ...shared,
  define: { ...shared.define, __WORKER_SOURCE__: JSON.stringify(workerCode) },
  build: {
    outDir: "public/sandbox/scene3d",
    emptyOutDir: false,
    minify: true,
    target: "es2022",
    copyPublicDir: false,
    lib: { entry: "src/sandbox/scene3d/frame.ts", formats: ["iife"], name: "SceneFrame", fileName: () => "frame.js" },
  },
});

console.log(`sandbox: frame.js built (worker ${(workerCode.length / 1024).toFixed(0)} KB)`);
