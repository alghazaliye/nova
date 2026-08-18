// Compiles a dart2wasm-generated main module from `source` which can then
// be instantiated via the `instantiate` method.
//
// `source` needs to be a `Response` object (or promise thereof) e.g. created
// via the `fetch()` JS API.
export async function compileStreaming(source) {
  const builtins = {builtins: ['js-string']};
  return new CompiledApp(
      await WebAssembly.compileStreaming(source, builtins), builtins);
}

// Compiles a dart2wasm-generated wasm module from `bytes` which is then
// instantiable via the `instantiate` method.
export async function compile(bytes) {
  const builtins = {builtins: ['js-string']};
  return new CompiledApp(await WebAssembly.compile(bytes, builtins), builtins);
}

class CompiledApp {
  constructor(module, builtins) {
    this.module = module;
    this.builtins = builtins;
  }

  // The second argument is an options object containing:
  // `loadDeferredModules` is a JS function that takes an array of module names
  //   matching wasm files produced by the dart2wasm compiler. It also takes a
  //   callback that should be invoked for each loaded module with 2 arguments:
  //   (1) the module name, (2) the loaded module in a format supported by
  //   `WebAssembly.compile` or `WebAssembly.compileStreaming`. The callback
  //   returns a Promise that resolves when the module is instantiated.
  //   loadDeferredModules should return a Promise that resolves when all the
  //   modules have been loaded and the callback promises have resolved.
  // `loadDeferredId` is a JS function that takes load ID produced by the
  //   compiler when the `use-load-ids` option is passed. Each load ID maps to
  //   one or more wasm files as specified in the emitted JSON file. It also
  //   takes a callback that should be invoked for each loaded module with 2
  //   arguments: (1) the module name, (2) the loaded module in a format
  //   supported by `WebAssembly.compile` or `WebAssembly.compileStreaming`.
  //   The callback returns a Promise that resolves when the module is
  //   instantiated.
  //   loadDeferredId should return a Promise that resolves when all the
  //   modules have been loaded and the callback promises have resolved.
  async instantiate(additionalImports, {loadDeferredModules, loadDeferredId} = {}) {
    let dartInstance;

    // Prints to the console
    function printToConsole(value) {
      if (typeof dartPrint == "function") {
        dartPrint(value);
        return;
      }
      if (typeof console == "object" && typeof console.log != "undefined") {
        console.log(value);
        return;
      }
      if (typeof print == "function") {
        print(value);
        return;
      }

      throw "Unable to print message: " + value;
    }

    // A special symbol attached to functions that wrap Dart functions.
    const jsWrappedDartFunctionSymbol = Symbol("JSWrappedDartFunction");

    function finalizeWrapper(dartFunction, wrapped) {
      wrapped.dartFunction = dartFunction;
      wrapped[jsWrappedDartFunctionSymbol] = true;
      return wrapped;
    }

    // Imports
    const dart2wasm = {
            AB: x0 => new Int16Array(x0),
      AC: (o, start, length) => new Uint8Array(o.buffer, o.byteOffset + start, length),
      AD: (x0,x1,x2) => x0.setAttribute(x1,x2),
      AE: (x0,x1) => x0.matchMedia(x1),
      AF: x0 => x0.tiltY,
      AG: x0 => x0.v8BreakIterator,
      AH: x0 => x0.minWidth,
      AI: x0 => x0.deref(),
      AJ: (x0,x1) => { x0.autoplay = x1 },
      AK: (x0,x1) => { x0.controls = x1 },
      AL: () => globalThis.document,
      AM: x0 => x0.style,
      AN: x0 => x0.duration,
      AO: x0 => x0.data,
      AP: x0 => x0.value,
      B: s => printToConsole(s),
      BB: x0 => new Uint16Array(x0),
      BC: (o, start, length) => new Int8Array(o.buffer, o.byteOffset + start, length),
      BD: x0 => x0.getBoundingClientRect(),
      BE: x0 => x0.matches,
      BF: x0 => x0.tiltX,
      BG: () => globalThis.Intl,
      BH: (x0,x1) => x0.removeProperty(x1),
      BI: () => globalThis.WeakRef,
      BJ: (x0,x1) => { x0.muted = x1 },
      BK: (x0,x1) => x0.removeAttribute(x1),
      BL: (x0,x1,x2,x3) => x0.open(x1,x2,x3),
      BM: (x0,x1) => { x0.autoplay = x1 },
      BN: (x0,x1) => { x0.playsInline = x1 },
      BO: (x0,x1) => { x0.onmessage = x1 },
      BP: x0 => x0.done,
      C: Function.prototype.call.bind(Number.prototype.toString),
      CB: x0 => new Int32Array(x0),
      CC: (x0,x1) => x0.querySelector(x1),
      CD: (ms, c) =>
      setTimeout(() => dartInstance.exports.$invokeCallback(c),ms),
      CE: o => typeof o === 'function' && o[jsWrappedDartFunctionSymbol] === true,
      CF: x0 => x0.pointerType,
      CG: (x0,x1) => x0.segment(x1),
      CH: (x0,x1) => x0.add(x1),
      CI: (o, offsetInBytes, lengthInBytes) => {
        var dst = new ArrayBuffer(lengthInBytes);
        new Uint8Array(dst).set(new Uint8Array(o, offsetInBytes, lengthInBytes));
        return new DataView(dst);
      },
      CJ: (x0,x1) => { x0.id = x1 },
      CK: x0 => x0.load(),
      CL: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      CM: (x0,x1) => { x0.src = x1 },
      CN: (x0,x1) => { x0.loop = x1 },
      CO: x0 => x0.port,
      CP: x0 => x0.read(),
      D: Function.prototype.call.bind(BigInt.prototype.toString),
      DB: (jsArray, jsArrayOffset, wasmArray, wasmArrayOffset, length) => {
        const getValue = dartInstance.exports.$wasmI32ArrayGet;
        for (let i = 0; i < length; i++) {
          jsArray[jsArrayOffset + i] = getValue(wasmArray, wasmArrayOffset + i);
        }
      },
      DC: (x0,x1) => x0.item(x1),
      DD: s => new Date(s * 1000).getTimezoneOffset() * 60,
      DE: f => f.dartFunction,
      DF: x0 => x0.pointerId,
      DG: x0 => x0.index,
      DH: x0 => x0.data,
      DI: (a, s, e) => a.slice(s, e),
      DJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      DK: x0 => x0.remove(),
      DL: (x0,x1,x2) => x0.addEventListener(x1,x2),
      DM: x0 => x0.pause(),
      DN: (x0,x1,x2) => x0.setItem(x1,x2),
      DO: x0 => x0.destination,
      DP: x0 => x0.body,
      E: (exn) => {
        let stackString = exn.toString();
        let frames = stackString.split('\n');
        let drop = 4;
        if (frames[0].startsWith('Error')) {
            drop += 1;
        }
        return frames.slice(drop).join('\n');
      },
      EB: x0 => new Uint32Array(x0),
      EC: x0 => x0.length,
      ED: Date.now,
      EE: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      EF: x0 => x0.getCoalescedEvents(),
      EG: x0 => x0.next(),
      EH: (x0,x1) => { x0.scrollTop = x1 },
      EI: x0 => x0.pop(),
      EJ: (x0,x1,x2) => x0.addEventListener(x1,x2),
      EK: x0 => x0.hasChildNodes(),
      EL: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      EM: x0 => x0.paused,
      EN: x0 => x0.localStorage,
      EO: (x0,x1) => x0.addModule(x1),
      EP: (x0,x1) => new OffscreenCanvas(x0,x1),
      F: () => new Error().stack,
      FB: x0 => new Float32Array(x0),
      FC: (x0,x1) => x0.querySelectorAll(x1),
      FD: (handle) => clearTimeout(handle),
      FE: (wasmFunction,f) => finalizeWrapper(f, function(x0,x1) { return wasmFunction(f,arguments.length,x0,x1) }),
      FF: (x0,x1) => x0.getModifierState(x1),
      FG: x0 => x0.value,
      FH: (x0,x1,x2) => x0.setSelectionRange(x1,x2),
      FI: () => {
        return typeof process != "undefined" &&
               Object.prototype.toString.call(process) == "[object process]" &&
               process.platform == "win32"
      },
      FJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      FK: (x0,x1) => x0.removeTrack(x1),
      FL: x0 => x0.send(),
      FM: () => {
        // On browsers return `globalThis.location.href`
        if (globalThis.location != null) {
          return globalThis.location.href;
        }
        return null;
      },
      FN: (x0,x1) => x0.getItem(x1),
      FO: x0 => ({parameterData: x0}),
      FP: x0 => x0.assetBase,
      G: s => JSON.stringify(s),
      GB: (jsArray, jsArrayOffset, wasmArray, wasmArrayOffset, length) => {
        const getValue = dartInstance.exports.$wasmF32ArrayGet;
        for (let i = 0; i < length; i++) {
          jsArray[jsArrayOffset + i] = getValue(wasmArray, wasmArrayOffset + i);
        }
      },
      GC: (x0,x1) => x0.getAttribute(x1),
      GD: (x0,x1) => x0.closest(x1),
      GE: (p, s, f) => p.then(s, (e) => f(e, e === undefined)),
      GF: s => s.trimLeft(),
      GG: x0 => x0.done,
      GH: (x0,x1) => { x0.value = x1 },
      GI: x0 => x0.abort(),
      GJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      GK: (x0,x1) => x0.replaceTrack(x1),
      GL: (x0,x1) => x0.revokeObjectURL(x1),
      GM: (x0,x1,x2) => x0.insertBefore(x1,x2),
      GN: (x0,x1) => x0.key(x1),
      GO: (x0,x1,x2) => new AudioWorkletNode(x0,x1,x2),
      GP: x0 => x0.loader,
      H: Function.prototype.call.bind(Number.prototype.toString),
      HB: x0 => new Float64Array(x0),
      HC: x0 => x0.remove(),
      HD: x0 => x0.bottom,
      HE: (o, i) => o[i],
      HF: (x0,x1) => x0[x1],
      HG: (o, m, a) => o[m].apply(o, a),
      HH: (x0,x1,x2) => x0.setSelectionRange(x1,x2),
      HI: () => new AbortController(),
      HJ: x0 => x0.muted,
      HK: x0 => x0.getSenders(),
      HL: (x0,x1) => { x0.src = x1 },
      HM: x0 => x0.id,
      HN: x0 => x0.length,
      HO: x0 => x0.audioWorklet,
      HP: () => globalThis._flutter,
      I: Function.prototype.call.bind(String.prototype.indexOf),
      IB: (jsArray, jsArrayOffset, wasmArray, wasmArrayOffset, length) => {
        const getValue = dartInstance.exports.$wasmF64ArrayGet;
        for (let i = 0; i < length; i++) {
          jsArray[jsArrayOffset + i] = getValue(wasmArray, wasmArrayOffset + i);
        }
      },
      IC: (x0,x1) => x0.appendChild(x1),
      ID: x0 => x0.top,
      IE: o => o.length,
      IF: x0 => x0.index,
      IG: x0 => x0.iterator,
      IH: (x0,x1) => { x0.value = x1 },
      II: (x0,x1,x2,x3,x4,x5) => ({method: x0,headers: x1,body: x2,credentials: x3,redirect: x4,signal: x5}),
      IJ: x0 => x0.enabled,
      IK: x0 => x0.label,
      IL: (x0,x1,x2,x3,x4) => globalThis.createImageBitmap(x0,x1,x2,x3,x4),
      IM: x0 => x0.offsetHeight,
      IN: (x0,x1) => x0.removeItem(x1),
      IO: x0 => globalThis.URL.revokeObjectURL(x0),
      J: (s, p, i) => s.lastIndexOf(p, i),
      JB: x0 => new ArrayBuffer(x0),
      JC: (x0,x1) => x0.append(x1),
      JD: x0 => x0.right,
      JE: o => {
        if (o === undefined) return 1;
        var type = typeof o;
        if (type === 'boolean') return 2;
        if (type === 'number') return 3;
        if (type === 'string') return 4;
        if (o instanceof Array) return 5;
        if (ArrayBuffer.isView(o)) {
          if (o instanceof Int8Array) return 6;
          if (o instanceof Uint8Array) return 7;
          if (o instanceof Uint8ClampedArray) return 8;
          if (o instanceof Int16Array) return 9;
          if (o instanceof Uint16Array) return 10;
          if (o instanceof Int32Array) return 11;
          if (o instanceof Uint32Array) return 12;
          if (o instanceof Float32Array) return 13;
          if (o instanceof Float64Array) return 14;
          if (o instanceof DataView) return 15;
        }
        if (o instanceof ArrayBuffer) return 16;
        // Feature check for `SharedArrayBuffer` before doing a type-check.
        if (globalThis.SharedArrayBuffer !== undefined &&
            o instanceof SharedArrayBuffer) {
            return 17;
        }
        if (o instanceof Promise) return 18;
        return 19;
      },
      JF: (x0,x1) => x0.exec(x1),
      JG: () => globalThis.Symbol,
      JH: s => {
        if (/[[\]{}()*+?.\\^$|]/.test(s)) {
            s = s.replace(/[[\]{}()*+?.\\^$|]/g, '\\$&');
        }
        return s;
      },
      JI: (x0,x1) => globalThis.fetch(x0,x1),
      JJ: x0 => x0.label,
      JK: x0 => x0.kind,
      JL: x0 => x0.naturalHeight,
      JM: x0 => x0.offsetWidth,
      JN: x0 => ({name: x0}),
      JO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      K: (exn) => {
        if (exn instanceof Error) {
          return exn.stack;
        } else {
          return null;
        }
      },
      KB: (x0,x1,x2) => new Uint8Array(x0,x1,x2),
      KC: (x0,x1,x2,x3) => x0.setProperty(x1,x2,x3),
      KD: x0 => x0.left,
      KE: x0 => x0.language,
      KF: s => s.toUpperCase(),
      KG: (x0,x1) => new Intl.Segmenter(x0,x1),
      KH: x0 => x0.value,
      KI: (x0,x1) => x0.get(x1),
      KJ: x0 => x0.id,
      KK: x0 => x0.groupId,
      KL: x0 => x0.naturalWidth,
      KM: x0 => x0.stopPropagation(),
      KN: (x0,x1) => x0.query(x1),
      KO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      L: o => o === undefined,
      LB: (x0,x1,x2) => new DataView(x0,x1,x2),
      LC: x0 => x0.style,
      LD: x0 => x0.clientY,
      LE: (x0,x1,x2,x3) => x0.register(x1,x2,x3),
      LF: x0 => x0.length,
      LG: x0 => x0.Segmenter,
      LH: x0 => x0.selectionDirection,
      LI: (wasmFunction,f) => finalizeWrapper(f, function(x0,x1,x2) { return wasmFunction(f,arguments.length,x0,x1,x2) }),
      LJ: (x0,x1) => { x0.srcObject = x1 },
      LK: x0 => x0.deviceId,
      LL: x0 => x0.decode(),
      LM: x0 => x0.disabled,
      LN: x0 => ({audio: x0}),
      LO: (x0,x1,x2) => x0.getCurrentPosition(x1,x2),
      M: o => String(o),
      MB: (o, p) => o[p],
      MC: x0 => x0.debugShowSemanticsNodes,
      MD: x0 => x0.clientX,
      ME: () => globalThis.window.FinalizationRegistry,
      MF: x0 => x0.flags,
      MG: x0 => x0.buffer,
      MH: x0 => x0.selectionStart,
      MI: (x0,x1) => x0.forEach(x1),
      MJ: (x0,x1,x2) => x0.addTrack(x1,x2),
      MK: x0 => x0.enumerateDevices(),
      ML: (x0,x1) => { x0.decoding = x1 },
      MM: (x0,x1) => { x0.min = x1 },
      MN: (o,s,v) => o[s] = v,
      MO: () => globalThis.Notification.requestPermission(),
      N: (c) =>
      queueMicrotask(() => dartInstance.exports.$invokeCallback(c)),
      NB: (o) => new DataView(o.buffer, o.byteOffset, o.byteLength),
      NC: o => o,
      ND: x0 => x0.changedTouches,
      NE: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      NF: (a, s) => a.join(s),
      NG: x0 => x0.wasmMemory,
      NH: x0 => x0.selectionEnd,
      NI: x0 => x0.name,
      NJ: x0 => x0.track,
      NK: (x0,x1) => { x0.enabled = x1 },
      NL: (x0,x1) => { x0.crossOrigin = x1 },
      NM: (x0,x1) => { x0.max = x1 },
      NN: () => Symbol("jsBoxedDartObjectProperty"),
      NO: x0 => ({video: x0}),
      O: (x0,x1) => x0.didCreateEngineInitializer(x1),
      OB: Function.prototype.call.bind(Object.getOwnPropertyDescriptor(DataView.prototype, 'byteLength').get),
      OC: o => {
        if (o === undefined || o === null) return 0;
        if (typeof o === 'boolean') return 1;
        return 2;
      },
      OD: x0 => x0.offsetY,
      OE: x0 => new window.FinalizationRegistry(x0),
      OF: (x0,x1) => x0.error(x1),
      OG: () => globalThis.window._flutter_skwasmInstance,
      OH: x0 => x0.value,
      OI: x0 => x0.statusText,
      OJ: (x0,x1) => x0.getUserMedia(x1),
      OK: x0 => x0.close(),
      OL: (x0,x1) => x0.createObjectURL(x1),
      OM: (x0,x1) => { x0.disabled = x1 },
      ON: x0 => x0.state,
      OO: x0 => x0.active,
      P: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      PB: o => o.byteOffset,
      PC: (x0,x1) => x0.warn(x1),
      PD: x0 => x0.offsetX,
      PE: (x0,x1) => x0.unregister(x1),
      PF: () => globalThis.console,
      PG: () => new TextDecoder(),
      PH: x0 => x0.selectionDirection,
      PI: x0 => x0.url,
      PJ: (x0,x1) => ({video: x0,audio: x1}),
      PK: x0 => x0.stop(),
      PL: x0 => x0.URL,
      PM: (x0,x1) => { x0.scrollLeft = x1 },
      PN: x0 => x0.permissions,
      PO: x0 => x0.geolocation,
      Q: (wasmFunction,f) => finalizeWrapper(f, function() { return wasmFunction(f,arguments.length) }),
      QB: o => o.buffer,
      QC: x0 => x0.console,
      QD: x0 => x0.type,
      QE: (x0,x1) => x0.contains(x1),
      QF: s => s.trimRight(),
      QG: (a, i) => a.splice(i, 1),
      QH: x0 => x0.selectionStart,
      QI: x0 => x0.status,
      QJ: x0 => x0.id,
      QK: (x0,x1) => x0.getRandomValues(x1),
      QL: x0 => new Blob(x0),
      QM: (x0,x1) => { x0.spellcheck = x1 },
      QN: x0 => x0.stop(),
      QO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      R: (x0,x1) => ({initializeEngine: x0,autoStart: x1}),
      RB: Function.prototype.call.bind(DataView.prototype.getUint8),
      RC: () => globalThis.window,
      RD: x0 => x0.maxTouchPoints,
      RE: (s) => +s,
      RF: x0 => x0.blur(),
      RG: a => a.pop(),
      RH: x0 => x0.selectionEnd,
      RI: x0 => x0.getReader(),
      RJ: x0 => x0.mediaDevices,
      RK: () => globalThis.crypto,
      RL: (x0,x1,x2,x3,x4) => ({type: x0,data: x1,premultiplyAlpha: x2,colorSpaceConversion: x3,preferAnimation: x4}),
      RM: (x0,x1) => { x0.disabled = x1 },
      RN: (x0,x1,x2) => ({mimeType: x0,audioBitsPerSecond: x1,bitsPerSecond: x2}),
      RO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      S: (wasmFunction,f) => finalizeWrapper(f, function(x0,x1) { return wasmFunction(f,arguments.length,x0,x1) }),
      SB: (b, o) => new DataView(b, o),
      SC: (o, c) => o instanceof c,
      SD: x0 => x0.platform,
      SE: s => {
        if (!/^\s*[+-]?(?:Infinity|NaN|(?:\.\d+|\d+(?:\.\d*)?)(?:[eE][+-]?\d+)?)\s*$/.test(s)) {
          return NaN;
        }
        return parseFloat(s);
      },
      SF: x0 => x0.button,
      SG: (map, o, v) => map.set(o, v),
      SH: x0 => x0.keyCode,
      SI: x0 => x0.read(),
      SJ: x0 => x0.navigator,
      SK: l => new DataView(new ArrayBuffer(l)),
      SL: x0 => new window.ImageDecoder(x0),
      SM: (x0,x1) => x0.transferFromImageBitmap(x1),
      SN: (x0,x1) => new MediaRecorder(x0,x1),
      SO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      T: x0 => new Promise(x0),
      TB: (b, o, l) => new DataView(b, o, l),
      TC: (string, token) => string.split(token),
      TD: x0 => x0.body,
      TE: s => s.trim(),
      TF: x0 => x0.innerHeight,
      TG: (map, o) => map.get(o),
      TH: (x0,x1) => x0.scrollIntoView(x1),
      TI: x0 => x0.value,
      TJ: () => globalThis.window,
      TK: () => new FileReader(),
      TL: x0 => x0.name,
      TM: (x0,x1) => x0.getContext(x1),
      TN: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      TO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      U: (x0,x1,x2) => x0.call(x1,x2),
      UB: Function.prototype.call.bind(DataView.prototype.getFloat64),
      UC: o => o instanceof Array,
      UD: () => globalThis.document,
      UE: x0 => x0.classList,
      UF: x0 => x0.innerWidth,
      UG: () => new WeakMap(),
      UH: x0 => x0.multiViewEnabled,
      UI: x0 => x0.done,
      UJ: x0 => x0.userAgent,
      UK: (x0,x1) => x0.readAsArrayBuffer(x1),
      UL: x0 => x0.repetitionCount,
      UM: (x0,x1) => { x0.height = x1 },
      UN: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      UO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      V: (constructor, args) => {
        const factoryFunction = constructor.bind.apply(
            constructor, [null, ...args]);
        return new factoryFunction();
      },
      VB: o => {
        if (o === null || o === undefined) return 0;
        if (o instanceof Float64Array) return 1;
        return 2;
      },
      VC: (a, i) => a[i],
      VD: (x0,x1,x2) => x0.addEventListener(x1,x2),
      VE: x0 => x0.preventDefault(),
      VF: x0 => x0.height,
      VG: x0 => x0.debugSkipFontRetryDelay,
      VH: (x0,x1) => x0.replaceWith(x1),
      VI: x0 => x0.cancel(),
      VJ: x0 => new RTCPeerConnection(x0),
      VK: x0 => x0.result,
      VL: x0 => x0.frameCount,
      VM: (x0,x1) => { x0.width = x1 },
      VN: (x0,x1) => x0.start(x1),
      VO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      W: x0 => new Array(x0),
      WB: Function.prototype.call.bind(DataView.prototype.setFloat64),
      WC: a => a.length,
      WD: x0 => x0.hasFocus(),
      WE: x0 => x0.parent,
      WF: x0 => x0.width,
      WG: x0 => x0.status,
      WH: (x0,x1) => { x0.type = x1 },
      WI: x0 => x0.body,
      WJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      WK: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      WL: x0 => x0.selectedTrack,
      WM: x0 => x0.height,
      WN: (x0,x1) => x0.createMediaStreamSource(x1),
      WO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      X: o => [o],
      XB: (t, s) => t.set(s),
      XC: (x0,x1) => x0.test(x1),
      XD: x0 => x0.relatedTarget,
      XE: x0 => x0.timeStamp,
      XF: x0 => x0.clientHeight,
      XG: (x0,x1,x2) => x0.set(x1,x2),
      XH: (x0,x1) => { x0.className = x1 },
      XI: x0 => x0.headers,
      XJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      XK: () => new XMLHttpRequest(),
      XL: x0 => x0.completed,
      XM: x0 => x0.width,
      XN: x0 => x0.createAnalyser(),
      XO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      Y: (o0, o1) => [o0, o1],
      YB: Function.prototype.call.bind(DataView.prototype.setFloat32),
      YC: x0 => x0.userAgent,
      YD: x0 => x0.shiftKey,
      YE: (x0,x1) => x0.hasAttribute(x1),
      YF: x0 => x0.clientWidth,
      YG: x0 => x0.arrayBuffer(),
      YH: (x0,x1) => { x0.tabIndex = x1 },
      YI: x0 => x0.signal,
      YJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      YK: (x0,x1,x2,x3) => x0.open(x1,x2,x3),
      YL: x0 => x0.ready,
      YM: x0 => x0.rasterEndMilliseconds,
      YN: (x0,x1) => x0.connect(x1),
      YO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      Z: (o0, o1, o2) => [o0, o1, o2],
      ZB: Function.prototype.call.bind(DataView.prototype.getFloat32),
      ZC: x0 => x0.navigator,
      ZD: (decoder, codeUnits) => decoder.decode(codeUnits),
      ZE: x0 => x0.buttons,
      ZF: (x0,x1) => { x0.content = x1 },
      ZG: o => {
        if (o === null || o === undefined) return 0;
        if (o instanceof ArrayBuffer) return 1;
        if (globalThis.SharedArrayBuffer !== undefined &&
            o instanceof SharedArrayBuffer) {
          return 2;
        }
        return 3;
      },
      ZH: (x0,x1) => { x0.name = x1 },
      ZI: (x0,x1) => ({type: x0,sdp: x1}),
      ZJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      ZK: x0 => x0.send(),
      ZL: x0 => x0.tracks,
      ZM: x0 => x0.rasterStartMilliseconds,
      ZN: (x0,x1) => { x0.smoothingTimeConstant = x1 },
      ZO: (x0,x1) => { x0.preload = x1 },
      a: (o0, o1, o2, o3) => [o0, o1, o2, o3],
      aB: o => {
        if (o === null || o === undefined) return 0;
        if (o instanceof Float32Array) return 1;
        return 2;
      },
      aC: Function.prototype.call.bind(String.prototype.toLowerCase),
      aD: () => new TextDecoder("utf-8", {fatal: true}),
      aE: x0 => x0.ctrlKey,
      aF: (x0,x1) => { x0.name = x1 },
      aG: (x0,x1) => x0.fetch(x1),
      aH: (x0,x1) => { x0.placeholder = x1 },
      aI: (x0,x1) => x0.setLocalDescription(x1),
      aJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      aK: x0 => x0.type,
      aL: x0 => x0.close(),
      aM: x0 => x0.imageBitmaps,
      aN: (x0,x1) => { x0.fftSize = x1 },
      aO: x0 => x0.src,
      b: (x0,x1,x2) => { x0[x1] = x2 },
      bB: Function.prototype.call.bind(DataView.prototype.getUint32),
      bC: Object.is,
      bD: () => new TextDecoder("utf-8", {fatal: false}),
      bE: x0 => x0.y,
      bF: x0 => x0.head,
      bG: x0 => x0.fontFallbackBaseUrl,
      bH: (x0,x1) => { x0.autocomplete = x1 },
      bI: (x0,x1) => x0.createAnswer(x1),
      bJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      bK: x0 => x0.response,
      bL: (x0,x1) => ({frameIndex: x0,completeFramesOnly: x1}),
      bM: x0 => x0.canvasKitMaximumSurfaces,
      bN: (x0,x1) => { x0.onstop = x1 },
      bO: (x0,x1) => x0.setSinkId(x1),
      c: o => o,
      cB: o => {
        if (o === null || o === undefined) return 0;
        if (o instanceof Uint32Array) return 1;
        return 2;
      },
      cC: x0 => x0.vendor,
      cD: (a, i, v) => a[i] = v,
      cE: x0 => x0.x,
      cF: (x0,x1) => x0.removeChild(x1),
      cG: (handle) => clearInterval(handle),
      cH: (x0,x1) => { x0.name = x1 },
      cI: x0 => x0.type,
      cJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      cK: (x0,x1) => { x0.responseType = x1 },
      cL: (x0,x1) => x0.decode(x1),
      cM: x0 => x0.nextSibling,
      cN: x0 => ({type: x0}),
      cO: (x0,x1) => x0.querySelector(x1),
      d: (o, p) => o[p],
      dB: Function.prototype.call.bind(DataView.prototype.getInt32),
      dC: (x0,x1) => x0.createTextNode(x1),
      dD: (jsArray, jsArrayOffset, wasmArray, wasmArrayOffset, length) => {
        const setValue = dartInstance.exports.$wasmI8ArraySet;
        for (let i = 0; i < length; i++) {
          setValue(wasmArray, wasmArrayOffset + i, jsArray[jsArrayOffset + i]);
        }
      },
      dE: x0 => x0.scrollTop,
      dF: x0 => x0.firstChild,
      dG: (ms, c) =>
      setInterval(() => dartInstance.exports.$invokeCallback(c), ms),
      dH: (x0,x1) => { x0.placeholder = x1 },
      dI: x0 => x0.sdp,
      dJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      dK: x0 => x0.vendor,
      dL: x0 => x0.displayHeight,
      dM: (x0,x1) => x0.debug(x1),
      dN: (x0,x1) => new Blob(x0,x1),
      dO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      e: () => globalThis,
      eB: o => {
        if (o === null || o === undefined) return 0;
        if (o instanceof Int32Array) return 1;
        return 2;
      },
      eC: (x0,x1) => { x0.id = x1 },
      eD: (jsArray, jsArrayOffset, wasmArray, wasmArrayOffset, length) => {
        const setValue = dartInstance.exports.$wasmI32ArraySet;
        for (let i = 0; i < length; i++) {
          setValue(wasmArray, wasmArrayOffset + i, jsArray[jsArrayOffset + i]);
        }
      },
      eE: x0 => x0.offsetTop,
      eF: x0 => x0.viewConstraints,
      eG: () => Date.now(),
      eH: (x0,x1) => { x0.action = x1 },
      eI: (x0,x1,x2) => ({candidate: x0,sdpMid: x1,sdpMLineIndex: x2}),
      eJ: x0 => x0.streams,
      eK: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      eL: x0 => x0.displayWidth,
      eM: x0 => x0.hostElement,
      eN: x0 => globalThis.URL.createObjectURL(x0),
      eO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      f: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      fB: o => o instanceof Uint16Array,
      fC: (x0,x1) => { x0.nonce = x1 },
      fD: x0 => x0.visibilityState,
      fE: x0 => x0.scrollLeft,
      fF: x0 => x0.hostElement,
      fG: (jsArray, jsArrayOffset, wasmArray, wasmArrayOffset, length) => {
        const setValue = dartInstance.exports.$wasmF32ArraySet;
        for (let i = 0; i < length; i++) {
          setValue(wasmArray, wasmArrayOffset + i, jsArray[jsArrayOffset + i]);
        }
      },
      fH: (x0,x1) => { x0.method = x1 },
      fI: (x0,x1) => x0.addIceCandidate(x1),
      fJ: x0 => x0.transceiver,
      fK: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      fL: x0 => x0.duration,
      fM: x0 => x0.location,
      fN: x0 => x0.arrayBuffer(),
      fO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      g: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      gB: Function.prototype.call.bind(DataView.prototype.getUint16),
      gC: x0 => x0.nonce,
      gD: (x0,x1,x2) => x0.removeEventListener(x1,x2),
      gE: x0 => x0.offsetLeft,
      gF: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      gG: (jsArray, jsArrayOffset, wasmArray, wasmArrayOffset, length) => {
        const setValue = dartInstance.exports.$wasmF64ArraySet;
        for (let i = 0; i < length; i++) {
          setValue(wasmArray, wasmArrayOffset + i, jsArray[jsArrayOffset + i]);
        }
      },
      gH: (x0,x1) => { x0.noValidate = x1 },
      gI: (x0,x1) => ({type: x0,sdp: x1}),
      gJ: x0 => x0.receiver,
      gK: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      gL: x0 => x0.image,
      gM: (x0,x1) => x0.getModifierState(x1),
      gN: x0 => x0.type,
      gO: (x0,x1) => { x0.onerror = x1 },
      h: (x0,x1) => ({addView: x0,removeView: x1}),
      hB: o => o instanceof Int16Array,
      hC: () => globalThis.window.flutterConfiguration,
      hD: x0 => x0.disconnect(),
      hE: x0 => x0.offsetParent,
      hF: x0 => ({runApp: x0}),
      hG: (x0,x1,x2,x3) => x0.pushState(x1,x2,x3),
      hH: (x0,x1) => x0.removeAttribute(x1),
      hI: (x0,x1) => x0.setRemoteDescription(x1),
      hJ: x0 => x0.track,
      hK: (x0,x1) => x0.removeChild(x1),
      hL: () => globalThis.window.ImageDecoder,
      hM: x0 => x0.metaKey,
      hN: x0 => x0.mimeType,
      hO: (x0,x1) => { x0.oncancel = x1 },
      i: (l, r) => l === r,
      iB: Function.prototype.call.bind(DataView.prototype.getInt16),
      iC: (x0,x1) => x0.attachShadow(x1),
      iD: x0 => new Intl.Locale(x0),
      iE: (o, p, r) => o.replace(p, () => r),
      iF: Function.prototype.call.bind(DataView.prototype.getBigInt64),
      iG: x0 => x0.history,
      iH: x0 => x0.isConnected,
      iI: (x0,x1) => x0.createOffer(x1),
      iJ: x0 => x0.connectionState,
      iK: x0 => x0.click(),
      iL: x0 => x0.status,
      iM: x0 => x0.altKey,
      iN: (x0,x1) => { x0.ondataavailable = x1 },
      iO: (x0,x1) => { x0.onchange = x1 },
      j: x0 => x0.random(),
      jB: o => o instanceof Uint8ClampedArray,
      jC: (x0,x1) => x0.createElement(x1),
      jD: x0 => x0.region,
      jE: (x0,x1) => { x0.lastIndex = x1 },
      jF: Function.prototype.call.bind(DataView.prototype.setBigInt64),
      jG: x0 => x0.search,
      jH: x0 => x0.click(),
      jI: x0 => x0.kind,
      jJ: x0 => x0.signalingState,
      jK: (o, a) => o + a,
      jL: x0 => x0.response,
      jM: x0 => x0.ctrlKey,
      jN: x0 => x0.data,
      jO: x0 => x0.lastModified,
      k: o => o,
      kB: o => {
        if (o === null || o === undefined) return 0;
        if (o instanceof Uint8Array) return 1;
        return 2;
      },
      kC: x0 => x0.scale,
      kD: x0 => x0.script,
      kE: (s, m) => {
        try {
          return new RegExp(s, m);
        } catch (e) {
          return String(e);
        }
      },
      kF: (o, start, length) => new BigInt64Array(o.buffer, o.byteOffset + start, length),
      kG: x0 => x0.location,
      kH: (x0,x1) => x0.getElementsByClassName(x1),
      kI: () => new MediaStream(),
      kJ: (x0,x1) => { x0.onicegatheringstatechange = x1 },
      kK: x0 => x0.children,
      kL: (x0,x1,x2) => x0.setRequestHeader(x1,x2),
      kM: x0 => x0.isComposing,
      kN: x0 => globalThis.MediaRecorder.isTypeSupported(x0),
      kO: x0 => x0.target,
      l: o => {
        if (o === undefined || o === null) return 0;
        if (typeof o === 'number') return 1;
        return 2;
      },
      lB: Function.prototype.call.bind(DataView.prototype.setInt32),
      lC: x0 => x0.visualViewport,
      lD: x0 => x0.language,
      lE: o => o instanceof RegExp,
      lF: () => typeof dartUseDateNowForTicks !== "undefined",
      lG: x0 => x0.pathname,
      lH: (x0,x1) => x0.dispatchEvent(x1),
      lI: x0 => x0.getVideoTracks(),
      lJ: x0 => x0.iceGatheringState,
      lK: x0 => x0.firstChild,
      lL: (x0,x1) => { x0.responseType = x1 },
      lM: x0 => x0.code,
      lN: x0 => x0.sampleRate,
      lO: (x0,x1) => x0.replaceChildren(x1),
      m: () => globalThis.Math,
      mB: Function.prototype.call.bind(DataView.prototype.setUint32),
      mC: x0 => x0.devicePixelRatio,
      mD: x0 => x0.languages,
      mE: x0 => x0.dotAll,
      mF: () => Date.now(),
      mG: (x0,x1,x2,x3) => x0.replaceState(x1,x2,x3),
      mH: (x0,x1) => x0.createEvent(x1),
      mI: (x0,x1) => x0.addTrack(x1),
      mJ: x0 => x0.iceConnectionState,
      mK: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      mL: () => new XMLHttpRequest(),
      mM: x0 => x0.repeat,
      mN: x0 => x0.channelCount,
      mO: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      n: (x0,x1) => x0.prepend(x1),
      nB: Function.prototype.call.bind(DataView.prototype.setInt16),
      nC: x0 => x0.height,
      nD: (x0,x1) => x0.observe(x1),
      nE: x0 => x0.unicode,
      nF: () => 1000 * performance.now(),
      nG: o => {
        const proto = Object.getPrototypeOf(o);
        return proto === Object.prototype || proto === null;
      },
      nH: (x0,x1,x2,x3) => x0.initEvent(x1,x2,x3),
      nI: x0 => x0.getAudioTracks(),
      nJ: x0 => x0.sdpMLineIndex,
      nK: (x0,x1,x2) => x0.removeEventListener(x1,x2),
      nL: x0 => x0.naturalHeight,
      nM: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      nN: x0 => ({sampleRate: x0}),
      nO: (x0,x1,x2,x3) => x0.toBlob(x1,x2,x3),
      o: (x0,x1,x2,x3) => x0.addEventListener(x1,x2,x3),
      oB: Function.prototype.call.bind(DataView.prototype.setUint16),
      oC: x0 => x0.width,
      oD: (wasmFunction,f) => finalizeWrapper(f, function(x0,x1) { return wasmFunction(f,arguments.length,x0,x1) }),
      oE: x0 => x0.ignoreCase,
      oF: (x0,x1) => x0.requestAnimationFrame(x1),
      oG: o => Object.keys(o),
      oH: x0 => x0.readText(),
      oI: (x0,x1) => x0.append(x1),
      oJ: x0 => x0.sdpMid,
      oK: (x0,x1) => x0.item(x1),
      oL: x0 => x0.naturalWidth,
      oM: (x0,x1) => { x0.volume = x1 },
      oN: x0 => new AudioContext(x0),
      oO: (x0,x1,x2,x3) => x0.drawImage(x1,x2,x3),
      p: b => !!b,
      pB: Function.prototype.call.bind(DataView.prototype.setUint8),
      pC: x0 => x0.screen,
      pD: x0 => new ResizeObserver(x0),
      pE: x0 => x0.multiline,
      pF: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      pG: x0 => x0.state,
      pH: x0 => x0.clipboard,
      pI: (x0,x1) => { x0.height = x1 },
      pJ: x0 => x0.candidate,
      pK: x0 => x0.size,
      pL: (x0,x1) => { x0.pointerEvents = x1 },
      pM: x0 => x0.currentTime,
      pN: () => new AudioContext(),
      pO: (x0,x1,x2,x3,x4,x5) => x0.drawImage(x1,x2,x3,x4,x5),
      q: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      qB: Function.prototype.call.bind(DataView.prototype.setInt8),
      qC: (string, times) => string.repeat(times),
      qD: (x0,x1) => x0.getPropertyValue(x1),
      qE: (o, p, r) => o.replaceAll(p, () => r),
      qF: x0 => x0.now(),
      qG: x0 => x0.hash,
      qH: (x0,x1) => x0.writeText(x1),
      qI: (x0,x1) => { x0.width = x1 },
      qJ: x0 => x0.candidate,
      qK: x0 => x0.name,
      qL: (x0,x1) => { x0.height = x1 },
      qM: (x0,x1) => x0.start(x1),
      qN: x0 => x0.sampleRate,
      qO: x0 => x0.height,
      r: (x0,x1) => x0.focus(x1),
      rB: Function.prototype.call.bind(DataView.prototype.getInt8),
      rC: o => {
        if (o === null || o === undefined) return 0;
        if (typeof(o) === 'string') return 1;
        return 2;
      },
      rD: x0 => globalThis.parseFloat(x0),
      rE: x0 => x0.deltaMode,
      rF: x0 => x0.performance,
      rG: x0 => x0.state,
      rH: x0 => x0.unlock(),
      rI: (x0,x1) => { x0.border = x1 },
      rJ: (x0,x1,x2) => x0.setAttribute(x1,x2),
      rK: x0 => x0.length,
      rL: (x0,x1) => { x0.width = x1 },
      rM: (x0,x1) => x0.end(x1),
      rN: x0 => x0.getSettings(),
      rO: x0 => x0.width,
      s: () => ({}),
      sB: o => {
        if (o === null || o === undefined) return 0;
        if (o instanceof Int8Array) return 1;
        return 2;
      },
      sC: x0 => x0.tabIndex,
      sD: (x0,x1) => x0.getComputedStyle(x1),
      sE: x0 => x0.deltaY,
      sF: x0 => new Uint8Array(x0),
      sG: (x0,x1) => x0.go(x1),
      sH: (x0,x1) => x0.lock(x1),
      sI: (x0,x1) => { x0.objectFit = x1 },
      sJ: x0 => x0.message,
      sK: x0 => x0.files,
      sL: x0 => x0.style,
      sM: x0 => x0.length,
      sN: x0 => x0.disconnect(),
      sO: (x0,x1) => x0.getContext(x1),
      t: (o, p, v) => o[p] = v,
      tB: (o, start, length) => new Float64Array(o.buffer, o.byteOffset + start, length),
      tC: (x0,x1) => x0.contains(x1),
      tD: x0 => x0.documentElement,
      tE: x0 => x0.deltaX,
      tF: (x0,x1,x2) => x0.slice(x1,x2),
      tG: (a, l) => a.length = l,
      tH: x0 => x0.orientation,
      tI: (x0,x1) => { x0.transform = x1 },
      tJ: x0 => x0.code,
      tK: (x0,x1) => { x0.accept = x1 },
      tL: x0 => x0.src,
      tM: x0 => x0.buffered,
      tN: x0 => x0.close(),
      tO: (x0,x1) => { x0.height = x1 },
      u: () => [],
      uB: (o, start, length) => new Float32Array(o.buffer, o.byteOffset + start, length),
      uC: x0 => x0.activeElement,
      uD: x0 => x0.computedStyleMap(),
      uE: x0 => x0.wheelDeltaY,
      uF: (x0,x1) => x0.decode(x1),
      uG: x0 => x0.parentElement,
      uH: (x0,x1) => x0.querySelector(x1),
      uI: x0 => x0.style,
      uJ: x0 => x0.error,
      uK: (x0,x1) => { x0.multiple = x1 },
      uL: x0 => x0.play(),
      uM: (x0,x1) => { x0.playbackRate = x1 },
      uN: (x0,x1) => x0.warn(x1),
      uO: (x0,x1) => { x0.width = x1 },
      v: (a, i) => a.push(i),
      vB: (o, start, length) => new Uint32Array(o.buffer, o.byteOffset + start, length),
      vC: x0 => x0.parentNode,
      vD: (x0,x1) => x0.get(x1),
      vE: x0 => x0.wheelDeltaX,
      vF: (x0,x1) => x0.adoptText(x1),
      vG: (x0,x1) => x0.querySelectorAll(x1),
      vH: (x0,x1) => { x0.title = x1 },
      vI: (x0,x1) => x0.getElementById(x1),
      vJ: x0 => x0.videoHeight,
      vK: (x0,x1) => { x0.draggable = x1 },
      vL: x0 => globalThis.HTMLVideoElement(x0),
      vM: x0 => x0.pause(),
      vN: () => globalThis.console,
      vO: x0 => x0.height,
      w: x0 => new Int8Array(x0),
      wB: (o, start, length) => new Int32Array(o.buffer, o.byteOffset + start, length),
      wC: x0 => x0.tagName,
      wD: (o, p) => p in o,
      wE: x0 => x0.key,
      wF: x0 => x0.first(),
      wG: (d, digits) => d.toFixed(digits),
      wH: (x0,x1) => x0.vibrate(x1),
      wI: x0 => x0.body,
      wJ: x0 => x0.videoWidth,
      wK: (x0,x1) => { x0.type = x1 },
      wL: x0 => x0.load(),
      wM: x0 => x0.play(),
      wN: x0 => x0.state,
      wO: x0 => x0.width,
      x: (jsArray, jsArrayOffset, wasmArray, wasmArrayOffset, length) => {
        const getValue = dartInstance.exports.$wasmI8ArrayGet;
        for (let i = 0; i < length; i++) {
          jsArray[jsArrayOffset + i] = getValue(wasmArray, wasmArrayOffset + i);
        }
      },
      xB: (o, start, length) => new Uint16Array(o.buffer, o.byteOffset + start, length),
      xC: x0 => x0.target,
      xD: (x0,x1) => { x0.textContent = x1 },
      xE: x0 => x0.identifier,
      xF: x0 => x0.next(),
      xG: x0 => x0.maxHeight,
      xH: x0 => x0.content,
      xI: (x0,x1) => { x0.display = x1 },
      xJ: (x0,x1,x2,x3) => x0.addEventListener(x1,x2,x3),
      xK: x0 => x0.decode(),
      xL: (x0,x1) => { x0.objectFit = x1 },
      xM: x0 => x0.message,
      xN: x0 => x0.state,
      xO: (x0,x1) => { x0.src = x1 },
      y: x0 => new Uint8Array(x0),
      yB: (o, start, length) => new Int16Array(o.buffer, o.byteOffset + start, length),
      yC: x0 => x0.clientY,
      yD: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      yE: x0 => x0.touches,
      yF: x0 => x0.current(),
      yG: x0 => x0.maxWidth,
      yH: x0 => x0.document,
      yI: (x0,x1) => x0.createElement(x1),
      yJ: (x0,x1,x2,x3) => x0.removeEventListener(x1,x2,x3),
      yK: (x0,x1) => { x0.src = x1 },
      yL: (x0,x1) => { x0.height = x1 },
      yM: (x0,x1) => { x0.currentTime = x1 },
      yN: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      yO: x0 => x0.length,
      z: x0 => new Uint8ClampedArray(x0),
      zB: (o, start, length) => new Uint8ClampedArray(o.buffer, o.byteOffset + start, length),
      zC: x0 => x0.clientX,
      zD: x0 => x0.matches,
      zE: x0 => x0.pressure,
      zF: (x0,x1) => new Intl.v8BreakIterator(x0,x1),
      zG: x0 => x0.minHeight,
      zH: x0 => new WeakRef(x0),
      zI: () => globalThis.document,
      zJ: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      zK: (x0,x1) => x0.createElement(x1),
      zL: (x0,x1) => { x0.width = x1 },
      zM: (x0,x1) => { x0.src = x1 },
      zN: (wasmFunction,f) => finalizeWrapper(f, function(x0) { return wasmFunction(f,arguments.length,x0) }),
      zO: x0 => x0.getReader(),

    };

    const baseImports = {
      _: dart2wasm,
      Math: Math,
      Date: Date,
      Object: Object,
      Array: Array,
      Reflect: Reflect,
      WebAssembly: {
        JSTag: WebAssembly.JSTag,
      },
      "": new Proxy({}, { get(_, prop) { return prop; } }),

    };

    const jsStringPolyfill = {
      "charCodeAt": (s, i) => s.charCodeAt(i),
      "compare": (s1, s2) => {
        if (s1 < s2) return -1;
        if (s1 > s2) return 1;
        return 0;
      },
      "concat": (s1, s2) => s1 + s2,
      "equals": (s1, s2) => s1 === s2,
      "fromCharCode": (i) => String.fromCharCode(i),
      "length": (s) => s.length,
      "substring": (s, a, b) => s.substring(a, b),
      "fromCharCodeArray": (a, start, end) => {
        if (end <= start) return '';

        const read = dartInstance.exports.$wasmI16ArrayGet;
        let result = '';
        let index = start;
        const chunkLength = Math.min(end - index, 500);
        let array = new Array(chunkLength);
        while (index < end) {
          const newChunkLength = Math.min(end - index, 500);
          for (let i = 0; i < newChunkLength; i++) {
            array[i] = read(a, index++);
          }
          if (newChunkLength < chunkLength) {
            array = array.slice(0, newChunkLength);
          }
          result += String.fromCharCode(...array);
        }
        return result;
      },
      "intoCharCodeArray": (s, a, start) => {
        if (s === '') return 0;

        const write = dartInstance.exports.$wasmI16ArraySet;
        for (var i = 0; i < s.length; ++i) {
          write(a, start++, s.charCodeAt(i));
        }
        return s.length;
      },
      "test": (s) => typeof s == "string",
    };


    

    dartInstance = await WebAssembly.instantiate(this.module, {
      ...baseImports,
      ...additionalImports,
      
      "wasm:js-string": jsStringPolyfill,
    });

    return new InstantiatedApp(this, dartInstance);
  }
}

class InstantiatedApp {
  constructor(compiledApp, instantiatedModule) {
    this.compiledApp = compiledApp;
    this.instantiatedModule = instantiatedModule;
  }

  // Call the main function with the given arguments.
  invokeMain(...args) {
    this.instantiatedModule.exports.$invokeMain(args);
  }
}
