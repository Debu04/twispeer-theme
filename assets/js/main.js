/**
 * twispeer-theme/assets/js/main.js
 *
 * Lightweight bootstrap: ensures TWISPEER is available and exposes small helpers.
 * Composer & Feed behaviors run from component files under assets/js/components/.
 *
 * IMPORTANT: keep this file minimal to avoid duplicate handlers.
 */

(function () {
  if ( typeof TWISPEER === 'undefined' ) {
    console.warn('TWISPEER config missing');
    // still continue — component scripts will check TWISPEER themselves
    window.TWISPEER = window.TWISPEER || {};
  }

  // Optional helper: safe logger for components
  window.TWISPEER_safeLog = function () {
    if ( window.console && typeof console.log === 'function' ) {
      console.log.apply(console, arguments);
    }
  };

  // No DOM event listeners here — composer/feed components handle their own bindings.
})();
