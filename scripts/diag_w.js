// هذا الكود يُحقن في المتصفح لاختبار منطق اختيار الـbuild
(async () => {
  const out = {};
  // feature detection mirroring bootstrap
  const K = () => navigator.vendor === "Google Inc." || navigator.userAgent.includes("Edg/") ? "blink"
    : navigator.vendor === "Apple Computer, Inc." ? "webkit"
    : navigator.vendor === "" && navigator.userAgent.includes("Firefox") ? "gecko" : "unknown";
  const L = K();
  const R = () => typeof ImageDecoder > "u" ? false : L === "blink";
  const B = () => typeof Intl.v8BreakIterator < "u" && typeof Intl.Segmenter < "u";
  const z = () => {
    const i = [0, 97, 115, 109, 1, 0, 0, 0, 1, 5, 1, 95, 1, 120, 0];
    return WebAssembly.validate(new Uint8Array(i));
  };
  const M = () => {
    const i = document.createElement("canvas");
    i.width = 1; i.height = 1;
    return i.getContext("webgl2") != null ? 2 : i.getContext("webgl") != null ? 1 : -1;
  };
  const w = {
    browserEngine: L,
    hasImageCodecs: R(),
    hasChromiumBreakIterators: B(),
    supportsWasmGC: z(),
    crossOriginIsolated: window.crossOriginIsolated,
    webGLVersion: M()
  };
  out.w = w;
  const P = w.supportsWasmGC, G = P && w.webGLVersion > 0;
  out.P = P; out.G = G;
  // engine allowList for blink is [0] (no restriction)
  // selector: m.compileTarget==="dart2wasm"&&!P || n.renderer&&n.renderer!=m.renderer ? false : o(m.renderer)
  // o("skwasm") = G && allowList(engine)
  // So with wasm build: needs P and G true; otherwise falls to canvaskit js build
  const builds = [{"compileTarget":"dart2wasm","renderer":"skwasm","mainWasmPath":"main.dart.wasm","jsSupportRuntimePath":"main.dart.mjs"},{"compileTarget":"dart2js","renderer":"canvaskit","mainJsPath":"main.dart.js"}];
  // emulate selector with n={} (no overrides)
  const _ = {blink: 0, gecko: 1, webkit: 1, unknown: 1};
  const a = _[w.browserEngine];
  const o = m => m === "skwasm" ? G && a : true;
  const d = m => m.compileTarget === "dart2wasm" && !P || undefined && undefined !== undefined ? false : o(m.renderer);
  // simplified actual logic: d = m => (m.compileTarget==="dart2wasm"&&!P) ? false : o(m.renderer)
  const d2 = m => (m.compileTarget === "dart2wasm" && !P) ? false : o(m.renderer);
  const u = builds.find(d2);
  out.selected = u ? u.compileTarget + "/" + u.renderer : null;
  return JSON.stringify(out);
})()
