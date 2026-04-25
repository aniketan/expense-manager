/**
 * Plain HTTP on a custom host (e.g. http://expense.com) is not a "secure context".
 * Browsers only expose crypto.randomUUID() in secure contexts (HTTPS or localhost),
 * so React/Recharts and our code would throw before render.
 */
(function installRandomUuidPolyfill() {
  const c = globalThis.crypto;
  if (!c || typeof c.randomUUID === 'function') {
    return;
  }
  c.randomUUID = function randomUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (ch) => {
      const r = (Math.random() * 16) | 0;
      const v = ch === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  };
})();
