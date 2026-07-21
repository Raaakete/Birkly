window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['code'] = {
  label: 'Code',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    return `<textarea class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" rows="${fieldConfig.rows || 12}" spellcheck="false">${String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>`;
  },
  getValue: function(fieldName) {
    var input = document.getElementById('field_' + fieldName);
    return input ? input.value : '';
  },
  setValue: function(fieldName, value) {
    var input = document.getElementById('field_' + fieldName);
    if (input) input.value = value || '';
  },
  renderConfig: function(config, onChange) {
    return null;
  }
};

(function() {
  if (window.__birklyCodeFieldtypeLoaded) {
    return;
  }
  window.__birklyCodeFieldtypeLoaded = true;

  function setStatus(statusNode, message, state) {
    if (!statusNode) {
      return;
    }

    statusNode.textContent = message;
    statusNode.classList.remove('is-valid', 'is-invalid');
    if (state) {
      statusNode.classList.add(state);
    }
  }

  function initField(root) {
    if (!root || root.dataset.codeBound === '1') {
      return;
    }

    var textarea = root.querySelector('.code-field-input');
    var statusNode = root.querySelector('.code-field-status');
    var mode = (root.getAttribute('data-code-mode') || 'text').toLowerCase();
    if (!textarea || !statusNode) {
      return;
    }

    root.dataset.codeBound = '1';

    if (mode !== 'json') {
      setStatus(statusNode, '', '');
      return;
    }

    function validateJson() {
      var value = textarea.value.trim();
      if (!value) {
        setStatus(statusNode, 'JSON will be validated on blur.', '');
        return true;
      }

      try {
        JSON.parse(value);
        setStatus(statusNode, 'Valid JSON.', 'is-valid');
        return true;
      } catch (error) {
        setStatus(statusNode, 'Invalid JSON: ' + error.message, 'is-invalid');
        return false;
      }
    }

    textarea.addEventListener('blur', validateJson);
    textarea.addEventListener('input', function() {
      setStatus(statusNode, 'JSON will be validated on blur.', '');
    });

    validateJson();
  }

  function initAll() {
    document.querySelectorAll('[data-code-field]').forEach(initField);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  if ('MutationObserver' in window) {
    new MutationObserver(initAll).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
