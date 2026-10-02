<?php
/**
 * Blocking theme bootstrap — must run before admin CSS paints (prevents dark-mode flash).
 */
?>
<script<?= $__birklyScriptNonceAttr ?? '' ?>>
(function () {
  try {
    var key = 'birkly_theme';
    var allowed = { light: 1, dark: 1, system: 1 };
    var theme = localStorage.getItem(key);
    if (!theme || !allowed[theme]) {
      theme = 'light';
    }
    document.documentElement.setAttribute('data-theme', theme);
  } catch (e) {
    /* localStorage blocked */
  }
})();
</script>
