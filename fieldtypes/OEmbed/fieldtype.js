window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['oembed'] = {
  label: 'oEmbed',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    return `<input type="url" class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" ${fieldConfig.required ? 'required' : ''} placeholder="${fieldConfig.placeholder || 'https://www.youtube.com/watch?v=...'}" value="${value ? String(value).replace(/"/g, '&quot;') : ''}">`;
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
  if (window.__birklyOembedFieldtypeLoaded) {
    return;
  }
  window.__birklyOembedFieldtypeLoaded = true;

  function sanitizeYoutubeId(value) {
    return /^[A-Za-z0-9_-]{6,20}$/.test(value || '') ? value : '';
  }

  function resolveEmbedUrl(value) {
    var input = String(value || '').trim();
    var url;
    var host;
    var path;
    var params;
    var match;

    if (!input) {
      return '';
    }

    try {
      url = new URL(input);
    } catch (error) {
      return '';
    }

    host = url.hostname.toLowerCase();
    path = url.pathname.replace(/^\/+|\/+$/g, '');

    if (host.indexOf('youtu.be') !== -1) {
      match = sanitizeYoutubeId(path);
      return match ? 'https://www.youtube.com/embed/' + match : '';
    }

    if (host.indexOf('youtube.com') !== -1 || host.indexOf('youtube-nocookie.com') !== -1) {
      match = path.match(/^(embed|shorts)\/([^/]+)$/);
      if (match) {
        var directId = sanitizeYoutubeId(match[2]);
        return directId ? 'https://www.youtube.com/embed/' + directId : '';
      }

      params = url.searchParams.get('v');
      match = sanitizeYoutubeId(params);
      return match ? 'https://www.youtube.com/embed/' + match : '';
    }

    if (host.indexOf('vimeo.com') !== -1) {
      match = path.match(/(?:video\/)?([0-9]+)$/);
      return match ? 'https://player.vimeo.com/video/' + match[1] : '';
    }

    return '';
  }

  function initField(root) {
    if (!root || root.dataset.oembedBound === '1') {
      return;
    }

    var input = root.querySelector('.oembed-field-input');
    var preview = root.querySelector('.oembed-field-preview');
    var frame = root.querySelector('.oembed-field-frame');
    if (!input || !preview || !frame) {
      return;
    }

    root.dataset.oembedBound = '1';

    function update(value, normalize) {
      var embedUrl = resolveEmbedUrl(value);
      if (normalize && embedUrl) {
        input.value = embedUrl;
      }

      if (!embedUrl) {
        preview.hidden = true;
        frame.removeAttribute('src');
        return;
      }

      preview.hidden = false;
      frame.src = embedUrl;
    }

    input.addEventListener('input', function() {
      update(input.value, false);
    });

    input.addEventListener('blur', function() {
      update(input.value, true);
    });

    update(input.value, false);
  }

  function initAll() {
    document.querySelectorAll('[data-oembed-field]').forEach(initField);
  }

  // The admin CSP may not allow the provider's frames; hide the preview instead of an empty box.
  document.addEventListener('securitypolicyviolation', function(event) {
    if (String(event.violatedDirective || '').indexOf('frame-src') !== 0 || !event.blockedURI) {
      return;
    }
    document.querySelectorAll('.oembed-field-frame').forEach(function(frame) {
      var src = frame.getAttribute('src') || '';
      if (src && src.indexOf(event.blockedURI) === 0) {
        frame.closest('.oembed-field-preview').hidden = true;
      }
    });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  if ('MutationObserver' in window) {
    new MutationObserver(initAll).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
