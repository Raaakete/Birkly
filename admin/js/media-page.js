function ADMIN_MEDIA_API() {
  if (typeof Birkly !== 'undefined' && typeof Birkly.mediaUrl === 'function') {
    return Birkly.mediaUrl();
  }
  if (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function') {
    return Birkly.url('/api/admin_media.php');
  }
  return '/api/admin_media.php';
}

function resolveMediaItemUrl(url) {
  if (!url) return '';
  if (/^https?:\/\//i.test(url)) return url;
  if (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function') {
    return Birkly.url(url.replace(/^\//, ''));
  }
  return url;
}

(function () {
  'use strict';

  const viewStorageKey = 'birkly-admin-media-view';
  const foldersCacheMs = 30000;
  let foldersCacheLoadedAt = 0;
  let uploadInProgress = false;
  const state = {
    currentMedia: [],
    currentMediaItem: null,
    currentFolder: 'all',
    folderData: [],
    selectedMoveFolder: '',
    selectedIds: new Set(),
    pendingMoveIds: [],
    currentView: 'grid',
  };

  function mdT(key, fallback, params) {
    if ((typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')) {
        return Birkly.i18n.t('admin.' + key, fallback, params);
    }
    let text = fallback != null ? fallback : key;
    if (params && typeof params === 'object') {
        Object.keys(params).forEach(function (name) {
            var value = String(params[name]);
            text = String(text).split('{{' + name + '}}').join(value);
            text = text.split('{' + name + '}').join(value);
        });
    }
    return text;
  }

  function icon(name, opts) {
    return (typeof Birkly !== 'undefined' && typeof Birkly.icon === 'function') ? Birkly.icon(name, opts) : '';
  }

  function getCSRFToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function getResultMessage(result, fallback) {
    return result && (result.message || result.error) ? (result.message || result.error) : fallback;
  }

  function openModal(id) {
    document.getElementById(id)?.classList.add('active');
  }

  function closeModal(id) {
    document.getElementById(id)?.classList.remove('active');
  }

  function normalizeMediaItem(item) {
    const focalPoint = item && typeof item.focal_point === 'object' && item.focal_point !== null
      ? item.focal_point
      : {};
    const cropRect = item && typeof item.crop_rect === 'object' && item.crop_rect !== null
      ? item.crop_rect
      : null;

    return {
      ...item,
      alt_text: item && item.alt_text ? item.alt_text : '',
      focal_point: {
        x: Number.isFinite(Number(focalPoint.x)) ? Number(focalPoint.x) : 50,
        y: Number.isFinite(Number(focalPoint.y)) ? Number(focalPoint.y) : 50,
      },
      crop_rect: cropRect ? {
        x: Number.isFinite(Number(cropRect.x)) ? Number(cropRect.x) : 0,
        y: Number.isFinite(Number(cropRect.y)) ? Number(cropRect.y) : 0,
        w: Number.isFinite(Number(cropRect.w)) ? Number(cropRect.w) : 100,
        h: Number.isFinite(Number(cropRect.h)) ? Number(cropRect.h) : 100,
      } : null,
      variants: item && typeof item.variants === 'object' && item.variants !== null ? item.variants : {},
    };
  }

  function notify(message, type) {
    if (window.BirklyFeedback && typeof window.BirklyFeedback.toast === 'function') {
      window.BirklyFeedback.toast(message, type);
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  }

  function showSuccess(message) {
    notify(message, 'success');
  }

  function showError(message) {
    notify(message, 'error');
  }

  function getConfirmUploadButton() {
    return document.querySelector('[data-media-action="confirm-upload"]');
  }

  function setUploadBusy(busy, statusText) {
    uploadInProgress = busy;
    const confirmBtn = getConfirmUploadButton();
    const statusEl = document.getElementById('upload-status-text');
    if (confirmBtn) {
      confirmBtn.disabled = busy;
      if (busy) {
        if (!confirmBtn.dataset.defaultLabel) {
          confirmBtn.dataset.defaultLabel = confirmBtn.textContent || '';
        }
        confirmBtn.textContent = statusText || mdT('media_js_uploading', 'Uploading…');
      } else if (confirmBtn.dataset.defaultLabel) {
        confirmBtn.textContent = confirmBtn.dataset.defaultLabel;
      }
    }
    if (statusEl) {
      statusEl.textContent = busy ? (statusText || '') : '';
      statusEl.hidden = !busy;
    }
  }

  async function parseMediaApiResponse(response) {
    const body = await response.text();
    if (!body || !body.trim()) {
      throw new Error(mdT('media_js_empty_response', 'Upload failed (empty server response). Check logs/server.log and try again.'));
    }
    try {
      return JSON.parse(body);
    } catch (parseError) {
      throw new Error(mdT('media_js_invalid_response', 'Upload failed (invalid server response). Check logs/server.log and try again.'));
    }
  }

  function uploadResponseToListItem(data) {
    if (!data || !data.id) {
      return null;
    }
    return normalizeMediaItem({
      id: data.id,
      name: data.name,
      title: data.title,
      url: data.url,
      type: data.type,
      category: data.category,
      size: data.size,
      size_formatted: data.size_formatted,
      uploaded_at: data.uploaded_at,
      uploaded_by: data.uploaded_by,
      folder: data.folder,
      alt_text: data.alt_text || '',
      focal_point: data.focal_point,
      dimensions: data.dimensions,
    });
  }

  function mergeUploadedItems(items) {
    items.forEach(function (item) {
      if (!item || !item.id) {
        return;
      }
      state.currentMedia = [item].concat(state.currentMedia.filter(function (row) {
        return row.id !== item.id;
      }));
    });
    const total = document.getElementById('media-total-count');
    if (total && state.currentFolder === 'all') {
      total.textContent = String(state.currentMedia.length);
    }
    pruneSelection();
    renderMediaCollection(getFilteredMedia());
  }

  function initMediaPage() {
    if (!document.getElementById('media-grid')) {
      return;
    }

    state.currentView = localStorage.getItem(viewStorageKey) === 'list' ? 'list' : 'grid';

    setupMediaPageActions();
    bindModalDismissals();
    setupDragAndDrop();
    setView(state.currentView, false);
    updateBreadcrumb();
    loadFolders();
    loadMedia();
  }

  function setupMediaPageActions() {
    document.querySelectorAll('[data-media-action="upload"]').forEach(function (el) {
      el.addEventListener('click', uploadMedia);
    });
    document.querySelectorAll('[data-media-action="create-folder"]').forEach(function (el) {
      el.addEventListener('click', createFolder);
    });
    document.querySelectorAll('[data-media-action="close-upload"]').forEach(function (el) {
      el.addEventListener('click', closeUploadModal);
    });
    document.querySelectorAll('[data-media-action="confirm-upload"]').forEach(function (el) {
      el.addEventListener('click', handleUpload);
    });
    document.querySelectorAll('[data-media-action="close-media"]').forEach(function (el) {
      el.addEventListener('click', closeMediaModal);
    });
    document.querySelectorAll('[data-media-action="open-move"]').forEach(function (el) {
      el.addEventListener('click', async function () {
        openMoveModal();
      });
    });
    document.querySelectorAll('[data-media-action="delete-media"]').forEach(function (el) {
      el.addEventListener('click', deleteCurrentMedia);
    });
    document.querySelectorAll('[data-media-action="download-media"]').forEach(function (el) {
      el.addEventListener('click', downloadCurrentMedia);
    });
    document.querySelectorAll('[data-media-action="replace-media"]').forEach(function (el) {
      el.addEventListener('click', triggerReplaceMediaFile);
    });
    document.querySelectorAll('[data-media-action="create-web-variant"]').forEach(function (el) {
      el.addEventListener('click', createWebVariantForCurrent);
    });
    document.querySelectorAll('[data-media-action="bake-crop-variant"]').forEach(function (el) {
      el.addEventListener('click', bakeCropVariantForCurrent);
    });
    const replaceInput = document.getElementById('media-replace-input');
    if (replaceInput) {
      replaceInput.addEventListener('change', handleReplaceMediaFileSelected);
    }
    document.querySelectorAll('[data-media-action="save-media"]').forEach(function (el) {
      el.addEventListener('click', saveCurrentMediaDetails);
    });
    document.querySelectorAll('[data-media-action="close-move"]').forEach(function (el) {
      el.addEventListener('click', closeMoveModal);
    });
    document.querySelectorAll('[data-media-action="confirm-move"]').forEach(function (el) {
      el.addEventListener('click', confirmMove);
    });
    document.querySelectorAll('[data-media-action="move-selected"]').forEach(function (el) {
      el.addEventListener('click', async function () {
        openMoveModal(Array.from(state.selectedIds));
      });
    });
    document.querySelectorAll('[data-media-action="delete-selected"]').forEach(function (el) {
      el.addEventListener('click', deleteSelectedMedia);
    });
    document.querySelectorAll('[data-media-action="clear-selection"]').forEach(function (el) {
      el.addEventListener('click', clearSelection);
    });

    document.querySelectorAll('[data-media-folder]').forEach(function (el) {
      el.addEventListener('click', async function () {
        navigateToFolder(el.getAttribute('data-media-folder') || '');
      });
    });

    document.getElementById('media-breadcrumb')?.addEventListener('click', async function (event) {
      const item = event.target.closest('[data-media-folder]');
      if (item) {
        navigateToFolder(item.getAttribute('data-media-folder') || '');
      }
    });

    document.getElementById('media-search')?.addEventListener('input', filterMedia);
    document.getElementById('type-filter')?.addEventListener('change', filterMedia);
    document.querySelectorAll('[data-media-view]').forEach(function (button) {
      button.addEventListener('click', async function () {
        setView(button.getAttribute('data-media-view') || 'grid', true);
      });
    });
  }

  function bindModalDismissals() {
    document.querySelectorAll('.modal').forEach(function (modal) {
      modal.addEventListener('click', async function (event) {
        if (event.target !== modal) return;
        if (modal.id === 'upload-modal') closeUploadModal();
        if (modal.id === 'media-modal') closeMediaModal();
        if (modal.id === 'move-modal') closeMoveModal();
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      if (document.getElementById('move-modal')?.classList.contains('active')) {
        closeMoveModal();
        return;
      }
      if (document.getElementById('media-modal')?.classList.contains('active')) {
        closeMediaModal();
        return;
      }
      if (document.getElementById('upload-modal')?.classList.contains('active')) {
        closeUploadModal();
      }
    });
  }

  function setupDragAndDrop() {
    const mediaMain = document.querySelector('.media-main');
    const dropZone = document.getElementById('media-drop-zone');
    if (!mediaMain || !dropZone) return;

    let dragDepth = 0;

    function showDropZone() {
      dropZone.classList.add('is-active');
      dropZone.setAttribute('aria-hidden', 'false');
    }

    function hideDropZone() {
      dragDepth = 0;
      dropZone.classList.remove('is-active');
      dropZone.setAttribute('aria-hidden', 'true');
    }

    mediaMain.addEventListener('dragenter', function (event) {
      event.preventDefault();
      dragDepth += 1;
      showDropZone();
    });

    mediaMain.addEventListener('dragover', function (event) {
      event.preventDefault();
      showDropZone();
    });

    mediaMain.addEventListener('dragleave', function (event) {
      event.preventDefault();
      dragDepth = Math.max(0, dragDepth - 1);
      if (dragDepth === 0 && !mediaMain.contains(event.relatedTarget)) {
        hideDropZone();
      }
    });

    mediaMain.addEventListener('drop', async function (event) {
      event.preventDefault();
      hideDropZone();

      const files = Array.from(event.dataTransfer?.files || []);
      if (!files.length) return;

      try {
        await uploadFiles(files);
      } catch (error) {
        showError(error.message || mdT('media_js_upload_failed', 'Upload failed.'));
      } finally {
        setUploadBusy(false);
      }
    });
  }

  function setView(view, persist) {
    state.currentView = view === 'list' ? 'list' : 'grid';
    const grid = document.getElementById('media-grid');
    if (grid) {
      grid.dataset.view = state.currentView;
    }

    document.querySelectorAll('[data-media-view]').forEach(function (button) {
      const active = button.getAttribute('data-media-view') === state.currentView;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    if (persist) {
      localStorage.setItem(viewStorageKey, state.currentView);
    }

    renderMediaCollection(getFilteredMedia());
  }

  function navigateToFolder(folder) {
    state.currentFolder = folder;
    updateBreadcrumb();
    highlightCurrentFolder();
    loadMedia();
  }

  function updateBreadcrumb() {
    const breadcrumb = document.getElementById('media-breadcrumb');
    if (!breadcrumb) return;

    // The folder list already says where you are; the path only helps inside a folder.
    breadcrumb.hidden = state.currentFolder === 'all' || !state.currentFolder;
    if (breadcrumb.hidden) {
      breadcrumb.innerHTML = '';
      return;
    }

    breadcrumb.innerHTML = '<button type="button" class="breadcrumb-item" data-media-folder="">' + escapeHtml(mdT('media_js_root_folder', 'Root folder')) + '</button>';

    const parts = state.currentFolder.split('/');
    let path = '';
    parts.forEach(function (part) {
      path += (path ? '/' : '') + part;
      breadcrumb.insertAdjacentHTML('beforeend', '<span class="breadcrumb-separator">/</span>');
      breadcrumb.insertAdjacentHTML(
        'beforeend',
        '<button type="button" class="breadcrumb-item is-current" data-media-folder="' + escapeHtml(path) + '">' + escapeHtml(part) + '</button>'
      );
    });
  }

  async function loadFolders(forceRefresh) {
    if (!forceRefresh && foldersCacheLoadedAt && (Date.now() - foldersCacheLoadedAt) < foldersCacheMs) {
      return;
    }
    try {
      const response = await fetch(ADMIN_MEDIA_API() + '?action=folders', { credentials: 'same-origin' });
      const result = await parseMediaApiResponse(response);
      if (result.status === 'ok' && Array.isArray(result.data)) {
        state.folderData = result.data.slice().sort(function (a, b) {
          return String(a.path || '').localeCompare(String(b.path || ''));
        });
        renderFolderTree();
        renderMoveFolderTree();
        foldersCacheLoadedAt = Date.now();
      }
    } catch (error) {
      console.error('Error loading folders:', error);
    }
  }

  function renderFolderTree() {
    const container = document.getElementById('folder-list-container');
    if (!container) return;

    container.innerHTML = '';

    state.folderData.forEach(function (folder) {
      const folderItem = document.createElement('div');
      folderItem.className = 'folder-item';
      folderItem.dataset.path = folder.path || '';
      folderItem.style.setProperty('--level', String(folder.level || 0));
      folderItem.addEventListener('click', async function () {
        navigateToFolder(folder.path || '');
      });

      folderItem.innerHTML =
        icon('folder') +
        '<span class="folder-name">' + escapeHtml(folder.name || '') + '</span>' +
        '<span class="folder-count">' + escapeHtml(folder.count || 0) + '</span>';

      const actions = document.createElement('div');
      actions.className = 'folder-actions';

      const addButton = document.createElement('button');
      addButton.type = 'button';
      addButton.className = 'icon-button is-small';
      addButton.setAttribute('aria-label', mdT('media_create_subfolder_in', 'Create subfolder in {name}', { name: folder.name || mdT('media_folder_fallback', 'folder') }));
      addButton.title = mdT('create_subfolder', 'Create subfolder');
      addButton.innerHTML = icon('plus');
      addButton.addEventListener('click', async function (event) {
        event.stopPropagation();
        createSubfolder(folder.path || '');
      });

      const renameButton = document.createElement('button');
      renameButton.type = 'button';
      renameButton.className = 'icon-button is-small';
      renameButton.setAttribute('aria-label', mdT('media_rename_folder_named', 'Rename folder {name}', { name: folder.name || mdT('media_folder_fallback', 'folder') }));
      renameButton.title = mdT('rename_folder', 'Rename folder');
      renameButton.innerHTML = icon('pencil');
      renameButton.addEventListener('click', async function (event) {
        event.stopPropagation();
        renameFolder(folder.path || '');
      });

      const deleteButton = document.createElement('button');
      deleteButton.type = 'button';
      deleteButton.className = 'icon-button is-small is-danger';
      deleteButton.setAttribute('aria-label', mdT('media_delete_folder_named', 'Delete folder {name}', { name: folder.name || mdT('media_folder_fallback', 'folder') }));
      deleteButton.title = mdT('delete_folder', 'Delete folder');
      deleteButton.innerHTML = icon('trash');
      deleteButton.addEventListener('click', async function (event) {
        event.stopPropagation();
        deleteFolderWithOptions(folder.path || '');
      });

      actions.appendChild(addButton);
      actions.appendChild(renameButton);
      actions.appendChild(deleteButton);
      folderItem.appendChild(actions);

      container.appendChild(folderItem);
    });

    highlightCurrentFolder();
  }

  function highlightCurrentFolder() {
    document.querySelectorAll('.folder-item').forEach(function (item) {
      item.classList.remove('active');
    });

    if (state.currentFolder === 'all') {
      document.querySelector('.folder-item.all')?.classList.add('active');
      return;
    }

    if (!state.currentFolder) {
      document.querySelector('.folder-item.root')?.classList.add('active');
      return;
    }

    document.querySelector('.folder-item[data-path="' + CSS.escape(state.currentFolder) + '"]')?.classList.add('active');
  }

  function renderMoveFolderTree() {
    const container = document.getElementById('folder-tree-move');
    if (!container) return;

    container.innerHTML = '';

    const rootItem = document.createElement('button');
    rootItem.type = 'button';
    rootItem.className = 'folder-item-move';
    rootItem.innerHTML =
      icon('folder') +
      '<span class="folder-name">' + escapeHtml(mdT('media_js_root_folder', 'Root folder')) + '</span>';
    rootItem.addEventListener('click', async function () {
      selectMoveFolder('');
    });
    container.appendChild(rootItem);

    state.folderData.forEach(function (folder) {
      const folderItem = document.createElement('button');
      folderItem.type = 'button';
      folderItem.className = 'folder-item-move';
      folderItem.dataset.path = folder.path || '';
      folderItem.style.setProperty('--level', String(folder.level || 0));
      folderItem.innerHTML =
        icon('folder') +
        '<span class="folder-name">' + escapeHtml(folder.name || '') + '</span>';
      folderItem.addEventListener('click', async function () {
        selectMoveFolder(folder.path || '');
      });
      container.appendChild(folderItem);
    });

    selectMoveFolder(state.selectedMoveFolder);
  }

  function selectMoveFolder(folder) {
    state.selectedMoveFolder = folder || '';
    document.querySelectorAll('.folder-item-move').forEach(function (item) {
      const path = item.dataset.path || '';
      const selected = path === state.selectedMoveFolder || (!state.selectedMoveFolder && !item.dataset.path);
      item.classList.toggle('is-selected', selected);
      item.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  }

  async function loadMedia() {
    const url = ADMIN_MEDIA_API() + '?action=list&folder=' + encodeURIComponent(state.currentFolder);

    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      const result = await response.json();

      if (result.status === 'ok' && Array.isArray(result.data)) {
        state.currentMedia = result.data.map(normalizeMediaItem);
        if (state.currentFolder === 'all') {
          const total = document.getElementById('media-total-count');
          if (total) total.textContent = String(state.currentMedia.length);
        }
        pruneSelection();
        renderMediaCollection(getFilteredMedia());
      } else {
        showError(mdT('media_js_err_load', 'Failed to load media:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));
      }
    } catch (error) {
      showError(mdT('media_js_err_load_generic', 'Error loading media:') + ' ' + error.message);
    }
  }

  function getFilteredMedia() {
    const search = (document.getElementById('media-search')?.value || '').toLowerCase();
    const type = document.getElementById('type-filter')?.value || '';

    return state.currentMedia.filter(function (item) {
      const matchesSearch = !search || String(item.name || '').toLowerCase().includes(search);
      const matchesType = !type || item.category === type;
      return matchesSearch && matchesType;
    });
  }

  function filterMedia() {
    renderMediaCollection(getFilteredMedia());
  }

  function renderMediaCollection(items) {
    const grid = document.getElementById('media-grid');
    if (!grid) return;

    grid.innerHTML = '';

    if (!items.length) {
      const searchActive = Boolean((document.getElementById('media-search')?.value || '').trim());
      const typeActive = Boolean((document.getElementById('type-filter')?.value || '').trim());
      const libraryEmpty = state.currentMedia.length === 0;
      grid.innerHTML = '';
      if (libraryEmpty && !searchActive && !typeActive && window.Birkly && typeof window.Birkly.renderEmptyState === 'function') {
        grid.appendChild(window.Birkly.renderEmptyState({
          title: mdT('media_empty_library_title', 'Your media library is empty'),
          hint: mdT('media_empty_library_hint', 'Upload images and files to use in entries and templates.'),
          primary: {
            label: mdT('upload_media', 'Upload media'),
            onClick: uploadMedia,
          },
          secondary: {
            label: mdT('guide_show_me_how', 'Show me how'),
            guideAction: 'first-content',
          },
          helpTopic: 'media-library',
        }));
      } else {
        grid.innerHTML = '<div class="empty-state empty-state--compact">' +
          '<p class="empty-state-title">' + escapeHtml(mdT('media_js_empty_title', 'No media found')) + '</p>' +
          '<p class="empty-state-text">' + escapeHtml(mdT('media_js_empty_hint', 'Upload files or change the current filters.')) + '</p></div>';
      }
      updateSelectionToolbar();
      return;
    }

    items.forEach(function (item) {
      grid.appendChild(createMediaCard(item));
    });

    syncSelectionUi();
    updateSelectionToolbar();
  }

  function mediaTypeLabel(item) {
    const source = String(item.name || item.url || '');
    const ext = String(item.ext || (source.indexOf('.') !== -1 ? source.split('.').pop() : '') || item.category || 'file');
    return ext.toUpperCase();
  }

  function createMediaCard(item) {
    const card = document.createElement('article');
    card.className = 'media-card';
    card.dataset.mediaId = item.id;
    card.classList.toggle('is-selected', state.selectedIds.has(item.id));

    const preview = document.createElement('button');
    preview.type = 'button';
    preview.className = 'media-card-thumb thumb';
    preview.setAttribute('aria-label', 'Open ' + (item.name || 'media item'));
    preview.innerHTML = getMediaThumbnailHTML(item);
    bindThumbnailFallback(preview);
    preview.addEventListener('click', async function () {
      viewMedia(item.id);
    });

    const checkboxLabel = document.createElement('label');
    checkboxLabel.className = 'media-card-check';
    checkboxLabel.innerHTML =
      '<input type="checkbox" class="media-select-checkbox" aria-label="Select ' + escapeHtml(item.name || 'media item') + '">';

    const checkbox = checkboxLabel.querySelector('input');
    checkbox.checked = state.selectedIds.has(item.id);
    checkbox.addEventListener('click', async function (event) {
      event.stopPropagation();
    });
    checkbox.addEventListener('change', function () {
      toggleMediaSelection(item.id, checkbox.checked);
    });

    const meta = document.createElement('div');
    meta.className = 'media-card-meta';

    const nameEl = document.createElement('button');
    nameEl.type = 'button';
    nameEl.className = 'media-card-name';
    nameEl.title = item.name || 'Untitled';
    nameEl.textContent = item.name || 'Untitled';
    nameEl.addEventListener('click', async function () {
      viewMedia(item.id);
    });

    const infoEl = document.createElement('span');
    infoEl.className = 'media-card-info';
    infoEl.textContent = mediaTypeLabel(item) + ' · ' + String(item.size_formatted || '0 B');

    meta.append(nameEl, infoEl);

    const extra = document.createElement('div');
    extra.className = 'media-card-extra';
    extra.innerHTML =
      '<span>' + escapeHtml(item.folder || mdT('media_js_root_folder', 'Root folder')) + '</span>' +
      '<span>' + escapeHtml(item.uploaded_at ? new Date(item.uploaded_at).toLocaleDateString() : '') + '</span>';

    card.append(checkboxLabel, preview, meta, extra);
    return card;
  }

  /** Swap a broken image for the generic icon without an inline onerror. */
  function bindThumbnailFallback(preview) {
    const image = preview.querySelector('img');
    const fallback = preview.querySelector('[data-thumb-fallback]');
    if (!image || !fallback) return;
    image.addEventListener('error', function () {
      image.remove();
      fallback.hidden = false;
    });
  }

  function getMediaThumbnailHTML(item) {
    if (!item) {
      return icon('content', { size: 26 });
    }

    const category = String(item.category || 'file');
    const url = resolveMediaItemUrl(String(item.url || ''));
    const name = escapeHtml(String(item.name || 'Untitled'));

    if (category === 'image' && url) {
      return '<img src="' + escapeHtml(url) + '" alt="' + name + '">' +
        '<div data-thumb-fallback hidden>' + icon('media', { size: 26 }) + '</div>';
    }

    if (category === 'video') {
      return icon('play', { size: 26 });
    }

    if (category === 'audio') {
      return icon('music', { size: 26 });
    }

    return icon('content', { size: 26 });
  }

  function uploadMedia() {
    const rootLabel = mdT('media_js_root_folder', 'Root folder');
    const folderDisplay = state.currentFolder === 'all' ? rootLabel : (state.currentFolder || rootLabel);
    const currentUploadFolder = document.getElementById('current-upload-folder');
    if (currentUploadFolder) {
      currentUploadFolder.textContent = folderDisplay;
    }
    openModal('upload-modal');
  }

  function closeUploadModal() {
    closeModal('upload-modal');
    document.getElementById('upload-form')?.reset();
  }

  async function handleUpload() {
    if (uploadInProgress) {
      return;
    }
    const input = document.getElementById('file-input');
    const files = Array.from(input?.files || []);
    if (!files.length) {
      showError(mdT('media_js_select_file_upload', 'Select a file to upload.'));
      return;
    }

    try {
      await uploadFiles(files);
      closeUploadModal();
    } catch (error) {
      showError(error.message || mdT('media_js_upload_failed', 'Upload failed.'));
    } finally {
      setUploadBusy(false);
    }
  }

  async function uploadFiles(files) {
    const uploadTitle = (document.getElementById('upload-title')?.value || '').trim();
    const uploadFolder = state.currentFolder === 'all' ? '' : state.currentFolder;
    const failures = [];
    const uploadedItems = [];
    const totalBytes = files.reduce(function (sum, file) {
      return sum + (file.size || 0);
    }, 0);

    if (totalBytes > 8 * 1024 * 1024) {
      notify(
        mdT(
          'media_js_large_upload_hint',
          'Large upload in progress — on the local PHP server other admin actions may pause until this finishes.'
        ),
        'info'
      );
    }

    setUploadBusy(true, mdT('media_js_uploading', 'Uploading…'));

    for (let index = 0; index < files.length; index += 1) {
      const file = files[index];
      if (files.length > 1) {
        setUploadBusy(true, mdT('media_js_uploading_file', 'Uploading {current} of {total}…', {
          current: String(index + 1),
          total: String(files.length),
        }));
      }
      const formData = new FormData();
      formData.append('file', file);
      formData.append('folder', uploadFolder);
      formData.append('_csrf', getCSRFToken());
      if (uploadTitle && files.length === 1) {
        formData.append('title', uploadTitle);
      }

      const response = await fetch(ADMIN_MEDIA_API() + '?action=upload', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });

      const result = await parseMediaApiResponse(response);
      if (result.status !== 'ok') {
        failures.push(file.name + ': ' + getResultMessage(result, 'Upload failed'));
        continue;
      }
      const listItem = uploadResponseToListItem(result.data);
      if (listItem) {
        uploadedItems.push(listItem);
      }
    }

    if (uploadedItems.length) {
      mergeUploadedItems(uploadedItems);
      foldersCacheLoadedAt = 0;
      void loadFolders(true);
      void loadMedia();
    }

    if (failures.length) {
      throw new Error(failures.join('\n'));
    }

    showSuccess(files.length === 1 ? 'Media uploaded successfully.' : 'Files uploaded successfully.');
  }

  async function createFolder() {
    const name = await BirklyFeedback.prompt('', '', {
      title: mdT('media_js_new_folder_title', 'New folder'),
      placeholder: mdT('media_js_folder_name_placeholder', 'Folder name'),
      confirmLabel: mdT('media_js_prompt_create_label', 'Create'),
    });
    if (!name) return;
    await createFolderRequest(name.trim());
  }

  async function createSubfolder(basePath) {
    const name = await BirklyFeedback.prompt('', '', {
      title: mdT('media_js_new_subfolder_title', 'New subfolder'),
      placeholder: mdT('media_js_subfolder_name_placeholder', 'Subfolder name'),
      confirmLabel: mdT('media_js_prompt_create_label', 'Create'),
    });
    if (!name) return;
    const folderName = basePath ? basePath + '/' + name.trim() : name.trim();
    await createFolderRequest(folderName);
  }

  async function createFolderRequest(folderName) {
    try {
      const formData = new FormData();
      formData.append('name', folderName);
      formData.append('_csrf', getCSRFToken());

      const response = await fetch(ADMIN_MEDIA_API() + '?action=create_folder', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });

      const result = await response.json();
      if (result.status === 'ok') {
        showSuccess(mdT('media_js_folder_created_success', 'Folder created successfully.'));
        await loadFolders();
      } else {
        showError(mdT('media_js_err_create_folder', 'Failed to create folder:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));
      }
    } catch (error) {
      showError(mdT('media_js_err_create_folder_generic', 'Error creating folder:') + ' ' + error.message);
    }
  }

  async function renameFolder(folderPath) {
    const currentName = folderPath.split('/').pop();
    const newName = await BirklyFeedback.prompt(mdT('media_js_rename_folder_message', 'Enter a new name for this folder.'), currentName, {
      title: mdT('rename_folder', 'Rename folder'),
      placeholder: 'Folder name',
      confirmLabel: 'Rename',
    });
    if (!newName || newName === currentName) return;

    const pathParts = folderPath.split('/');
    pathParts[pathParts.length - 1] = newName.trim();
    const newPath = pathParts.join('/');

    try {
      const formData = new FormData();
      formData.append('old_name', folderPath);
      formData.append('new_name', newPath);
      formData.append('_csrf', getCSRFToken());

      const response = await fetch(ADMIN_MEDIA_API() + '?action=rename_folder', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });

      const result = await response.json();
      if (result.status === 'ok') {
        showSuccess(mdT('media_js_folder_renamed_success', 'Folder renamed successfully.'));
        if (state.currentFolder === folderPath) {
          state.currentFolder = newPath;
        }
        await Promise.all([loadFolders(), loadMedia()]);
      } else {
        showError(mdT('media_js_err_rename_folder', 'Failed to rename folder:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));
      }
    } catch (error) {
      showError(mdT('media_js_err_rename_folder_generic', 'Error renaming folder:') + ' ' + error.message);
    }
  }

  async function deleteFolderWithOptions(folderPath) {
    const folderName = folderPath.split('/').pop();
    const folder = state.folderData.find(function (item) {
      return item.path === folderPath;
    });
    const hasFiles = folder && Number(folder.count || 0) > 0;
    let handleFiles = 'move';

    if (hasFiles) {
      const choice = await BirklyFeedback.confirm(mdT('media_js_confirm_folder_has_files', 'Folder "{name}" contains files.\n\nClick OK to move files to root, or Cancel to delete them with the folder.', { name: folderName }));
      handleFiles = choice ? 'move' : 'delete';
    }

    const confirmMessage = hasFiles
      ? (handleFiles === 'delete' ? mdT('media_js_confirm_delete_folder_and_files', 'Delete folder "{name}" and delete its files?', { name: folderName }) : mdT('media_js_confirm_delete_folder_move_files', 'Delete folder "{name}" and move its files to root?', { name: folderName }))
      : mdT('media_js_confirm_delete_folder', 'Delete folder "{name}"?', { name: folderName });

    if (!(await BirklyFeedback.confirm(confirmMessage))) return;

    try {
      const formData = new FormData();
      formData.append('name', folderPath);
      formData.append('handle_files', handleFiles);
      formData.append('_csrf', getCSRFToken());

      const response = await fetch(ADMIN_MEDIA_API() + '?action=delete_folder', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });

      const result = await response.json();
      if (result.status === 'ok') {
        showSuccess(getResultMessage(result, 'Folder deleted successfully.'));
        if (state.currentFolder === folderPath) {
          state.currentFolder = '';
        }
        await Promise.all([loadFolders(), loadMedia()]);
      } else {
        showError(mdT('media_js_err_delete_folder', 'Failed to delete folder:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));
      }
    } catch (error) {
      showError(mdT('media_js_err_delete_folder_generic', 'Error deleting folder:') + ' ' + error.message);
    }
  }

  function viewMedia(id) {
    const item = state.currentMedia.find(function (entry) {
      return entry.id === id;
    });
    if (!item) {
      showError(mdT('media_js_item_not_found', 'Media item not found.'));
      return;
    }

    state.currentMediaItem = normalizeMediaItem(item);
    document.getElementById('media-modal-title').textContent = item.name || 'Media details';
    renderMediaModalBody(state.currentMediaItem);
    updateMediaSheetActions(state.currentMediaItem);
    openModal('media-modal');
  }

  function renderMediaModalBody(item) {
    const body = document.getElementById('media-modal-body');
    if (!body) return;

    const uploadedAt = item.uploaded_at ? new Date(item.uploaded_at).toLocaleString() : '-';
    const folderLabel = item.folder || 'Root';
    const focalX = Math.max(0, Math.min(100, Math.round(Number(item.focal_point?.x || 50))));
    const focalY = Math.max(0, Math.min(100, Math.round(Number(item.focal_point?.y || 50))));
    const crop = item.crop_rect || null;
    const cropX = crop ? Math.max(0, Math.min(100, Math.round(Number(crop.x)))) : 0;
    const cropY = crop ? Math.max(0, Math.min(100, Math.round(Number(crop.y)))) : 0;
    const cropW = crop ? Math.max(1, Math.min(100, Math.round(Number(crop.w)))) : 100;
    const cropH = crop ? Math.max(1, Math.min(100, Math.round(Number(crop.h)))) : 100;
    const webVariant = item.variants && item.variants.web ? item.variants.web : null;
    const cropVariant = item.variants && item.variants.crop ? item.variants.crop : null;
    const variantNotes = [];
    if (webVariant) {
      variantNotes.push(mdT('media_js_web_variant_ready', 'Web variant: {size}', {
        size: webVariant.size_formatted || formatBytes(Number(webVariant.size || 0)),
      }));
    }
    if (cropVariant) {
      variantNotes.push(mdT('media_js_crop_variant_ready', 'Baked crop variant: {size}', {
        size: cropVariant.size_formatted || formatBytes(Number(cropVariant.size || 0)),
      }));
    }
    const variantNote = variantNotes.join(' · ');

    const details = [
      ['Name', item.name || ''],
      ['Type', item.type || ''],
      ['Size', item.size_formatted || ''],
      ['Uploaded', uploadedAt],
      ['Folder', folderLabel],
      ['ID', item.id || ''],
    ];
    if (item.dimensions && item.dimensions.width && item.dimensions.height) {
      details.push(['Dimensions', item.dimensions.width + ' × ' + item.dimensions.height]);
    }
    const detailRows = details.map(function (pair) {
      return '<div class="media-detail-row"><span class="media-detail-label">' + escapeHtml(pair[0]) + '</span>' +
        '<span class="media-detail-value">' + escapeHtml(pair[1]) + '</span></div>';
    }).join('');

    body.innerHTML =
      '<div class="media-sheet">' +
        '<div class="media-sheet-preview well">' + getMediaPreviewHTML(item) + '</div>' +
        '<div class="media-detail-rows">' + detailRows + '</div>' +
        '<div class="field-group">' +
          '<label class="field-label" for="media-alt-text">' + escapeHtml(mdT('media_js_alt_text', 'Alt text')) + '</label>' +
          '<input type="text" id="media-alt-text" class="input" value="' + escapeHtml(item.alt_text || '') + '" placeholder="' + escapeHtml(mdT('media_js_alt_text_placeholder', 'Describe the image for screen readers')) + '">' +
        '</div>' +
        '<div class="media-sheet-focal">' +
          '<div class="field-group">' +
            '<label class="field-label" for="media-focal-x">' + escapeHtml(mdT('media_js_focal_x', 'Focal point X')) + '</label>' +
            '<input type="number" id="media-focal-x" class="input is-mono" min="0" max="100" value="' + escapeHtml(focalX) + '">' +
          '</div>' +
          '<div class="field-group">' +
            '<label class="field-label" for="media-focal-y">' + escapeHtml(mdT('media_js_focal_y', 'Focal point Y')) + '</label>' +
            '<input type="number" id="media-focal-y" class="input is-mono" min="0" max="100" value="' + escapeHtml(focalY) + '">' +
          '</div>' +
        '</div>' +
        '<div class="media-sheet-crop">' +
          '<p class="field-help">' + escapeHtml(mdT('media_js_crop_help', 'Crop box uses percent of the original image (non-destructive). Drag the box or click the image to set focal point.')) + '</p>' +
          '<div class="media-sheet-focal">' +
            '<div class="field-group">' +
              '<label class="field-label" for="media-crop-x">' + escapeHtml(mdT('media_js_crop_x', 'Crop X')) + '</label>' +
              '<input type="number" id="media-crop-x" class="input is-mono" min="0" max="100" value="' + escapeHtml(cropX) + '">' +
            '</div>' +
            '<div class="field-group">' +
              '<label class="field-label" for="media-crop-y">' + escapeHtml(mdT('media_js_crop_y', 'Crop Y')) + '</label>' +
              '<input type="number" id="media-crop-y" class="input is-mono" min="0" max="100" value="' + escapeHtml(cropY) + '">' +
            '</div>' +
            '<div class="field-group">' +
              '<label class="field-label" for="media-crop-w">' + escapeHtml(mdT('media_js_crop_w', 'Crop width')) + '</label>' +
              '<input type="number" id="media-crop-w" class="input is-mono" min="1" max="100" value="' + escapeHtml(cropW) + '">' +
            '</div>' +
            '<div class="field-group">' +
              '<label class="field-label" for="media-crop-h">' + escapeHtml(mdT('media_js_crop_h', 'Crop height')) + '</label>' +
              '<input type="number" id="media-crop-h" class="input is-mono" min="1" max="100" value="' + escapeHtml(cropH) + '">' +
            '</div>' +
          '</div>' +
        '</div>' +
        (variantNote ? '<p class="field-help">' + escapeHtml(variantNote) + '</p>' : '') +
      '</div>';

    const replaceInput = document.getElementById('media-replace-input');
    if (replaceInput && !body.contains(replaceInput)) {
      body.appendChild(replaceInput);
    }

    initMediaFramingEditor();
  }

  let mediaFramingCleanup = null;

  function clampPercent(value, min, max) {
    return Math.max(min, Math.min(max, Math.round(value)));
  }

  function hasPartialCropRect(crop) {
    if (!crop) {
      return false;
    }
    return Number(crop.x) > 0 || Number(crop.y) > 0 || Number(crop.w) < 100 || Number(crop.h) < 100;
  }

  function normalizeCropRect(crop) {
    const x = clampPercent(Number(crop?.x ?? 0), 0, 100);
    const y = clampPercent(Number(crop?.y ?? 0), 0, 100);
    let w = clampPercent(Number(crop?.w ?? 100), 1, 100);
    let h = clampPercent(Number(crop?.h ?? 100), 1, 100);
    w = Math.min(w, 100 - x);
    h = Math.min(h, 100 - y);
    return { x: x, y: y, w: w, h: h };
  }

  function readFramingFromDom() {
    return {
      focal: {
        x: clampPercent(Number(document.getElementById('media-focal-x')?.value || 50), 0, 100),
        y: clampPercent(Number(document.getElementById('media-focal-y')?.value || 50), 0, 100),
      },
      crop: normalizeCropRect({
        x: document.getElementById('media-crop-x')?.value,
        y: document.getElementById('media-crop-y')?.value,
        w: document.getElementById('media-crop-w')?.value,
        h: document.getElementById('media-crop-h')?.value,
      }),
    };
  }

  function writeFramingToDom(framing) {
    const focalX = document.getElementById('media-focal-x');
    const focalY = document.getElementById('media-focal-y');
    const cropX = document.getElementById('media-crop-x');
    const cropY = document.getElementById('media-crop-y');
    const cropW = document.getElementById('media-crop-w');
    const cropH = document.getElementById('media-crop-h');
    if (focalX) focalX.value = String(framing.focal.x);
    if (focalY) focalY.value = String(framing.focal.y);
    if (cropX) cropX.value = String(framing.crop.x);
    if (cropY) cropY.value = String(framing.crop.y);
    if (cropW) cropW.value = String(framing.crop.w);
    if (cropH) cropH.value = String(framing.crop.h);
  }

  function applyFramingOverlay(stage, framing) {
    const box = stage.querySelector('[data-media-crop-box]');
    const handle = stage.querySelector('[data-media-crop-handle]');
    const focal = stage.querySelector('[data-media-crop-focal]');
    if (!box || !handle || !focal) {
      return;
    }
    box.style.left = framing.crop.x + '%';
    box.style.top = framing.crop.y + '%';
    box.style.width = framing.crop.w + '%';
    box.style.height = framing.crop.h + '%';
    handle.style.left = 'calc(' + (framing.crop.x + framing.crop.w) + '% - 6px)';
    handle.style.top = 'calc(' + (framing.crop.y + framing.crop.h) + '% - 6px)';
    focal.style.left = framing.focal.x + '%';
    focal.style.top = framing.focal.y + '%';
  }

  function initMediaFramingEditor() {
    if (mediaFramingCleanup) {
      mediaFramingCleanup();
      mediaFramingCleanup = null;
    }

    const stage = document.querySelector('[data-media-crop-stage]');
    if (!stage) {
      return;
    }

    const box = stage.querySelector('[data-media-crop-box]');
    const handle = stage.querySelector('[data-media-crop-handle]');
    const image = stage.querySelector('.media-crop-image');
    if (!box || !handle || !image) {
      return;
    }

    let dragMode = null;
    let dragStart = null;
    let framingStart = null;

    function refreshOverlay() {
      applyFramingOverlay(stage, readFramingFromDom());
      if (state.currentMediaItem) {
        updateMediaSheetActions(state.currentMediaItem);
      }
    }

    function onPointerMove(event) {
      if (!dragMode || !dragStart || !framingStart) {
        return;
      }
      const rect = stage.getBoundingClientRect();
      if (rect.width < 1 || rect.height < 1) {
        return;
      }
      const dx = ((event.clientX - dragStart.x) / rect.width) * 100;
      const dy = ((event.clientY - dragStart.y) / rect.height) * 100;
      let next = {
        focal: { x: framingStart.focal.x, y: framingStart.focal.y },
        crop: {
          x: framingStart.crop.x,
          y: framingStart.crop.y,
          w: framingStart.crop.w,
          h: framingStart.crop.h,
        },
      };
      if (dragMode === 'move') {
        next.crop.x = clampPercent(framingStart.crop.x + dx, 0, 100 - framingStart.crop.w);
        next.crop.y = clampPercent(framingStart.crop.y + dy, 0, 100 - framingStart.crop.h);
      } else if (dragMode === 'resize') {
        next.crop.w = clampPercent(framingStart.crop.w + dx, 1, 100 - framingStart.crop.x);
        next.crop.h = clampPercent(framingStart.crop.h + dy, 1, 100 - framingStart.crop.y);
      }
      next.crop = normalizeCropRect(next.crop);
      writeFramingToDom(next);
      applyFramingOverlay(stage, next);
    }

    function endDrag() {
      dragMode = null;
      dragStart = null;
      framingStart = null;
      window.removeEventListener('pointermove', onPointerMove);
      window.removeEventListener('pointerup', endDrag);
      if (state.currentMediaItem) {
        updateMediaSheetActions(state.currentMediaItem);
      }
    }

    function startDrag(mode, event) {
      event.preventDefault();
      dragMode = mode;
      dragStart = { x: event.clientX, y: event.clientY };
      framingStart = readFramingFromDom();
      window.addEventListener('pointermove', onPointerMove);
      window.addEventListener('pointerup', endDrag);
    }

    function onImageClick(event) {
      if (event.target !== image) {
        return;
      }
      const rect = stage.getBoundingClientRect();
      const x = clampPercent(((event.clientX - rect.left) / rect.width) * 100, 0, 100);
      const y = clampPercent(((event.clientY - rect.top) / rect.height) * 100, 0, 100);
      const framing = readFramingFromDom();
      framing.focal = { x: x, y: y };
      writeFramingToDom(framing);
      applyFramingOverlay(stage, framing);
    }

    const onBoxDown = function (event) {
      startDrag('move', event);
    };
    const onHandleDown = function (event) {
      event.stopPropagation();
      startDrag('resize', event);
    };

    box.addEventListener('pointerdown', onBoxDown);
    handle.addEventListener('pointerdown', onHandleDown);
    image.addEventListener('click', onImageClick);

    ['media-focal-x', 'media-focal-y', 'media-crop-x', 'media-crop-y', 'media-crop-w', 'media-crop-h'].forEach(function (id) {
      const input = document.getElementById(id);
      if (input) {
        input.addEventListener('change', refreshOverlay);
        input.addEventListener('input', refreshOverlay);
      }
    });

    refreshOverlay();

    mediaFramingCleanup = function () {
      box.removeEventListener('pointerdown', onBoxDown);
      handle.removeEventListener('pointerdown', onHandleDown);
      image.removeEventListener('click', onImageClick);
      endDrag();
    };
  }

  function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 1) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const pow = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    return (bytes / Math.pow(1024, pow)).toFixed(pow === 0 ? 0 : 1) + ' ' + units[pow];
  }

  function isRasterImageItem(item) {
    if (!item || item.category !== 'image') return false;
    return String(item.type || '') !== 'image/svg+xml';
  }

  function updateMediaSheetActions(item) {
    const variantBtn = document.querySelector('[data-media-action="create-web-variant"]');
    const bakeBtn = document.querySelector('[data-media-action="bake-crop-variant"]');
    const raster = isRasterImageItem(item);
    if (variantBtn) {
      variantBtn.hidden = !raster;
    }
    if (bakeBtn) {
      const crop = readFramingFromDom().crop;
      bakeBtn.hidden = !raster || !hasPartialCropRect(crop);
    }
  }

  function getMediaPreviewHTML(item) {
    const url = resolveMediaItemUrl(item.url || '');
    const type = item.type || '';
    const category = item.category || '';
    const altText = escapeHtml(item.alt_text || item.name || '');

    if (category === 'image') {
      return '<div class="media-crop-stage" data-media-crop-stage>' +
        '<img class="media-crop-image" src="' + escapeHtml(url) + '" alt="' + altText + '">' +
        '<div class="media-crop-box" data-media-crop-box></div>' +
        '<div class="media-crop-handle" data-media-crop-handle aria-hidden="true"></div>' +
        '<div class="media-crop-focal" data-media-crop-focal aria-hidden="true"></div>' +
        '</div>';
    }
    if (category === 'video') {
      return '<video controls><source src="' + escapeHtml(url) + '" type="' + escapeHtml(type) + '">Your browser does not support the video tag.</video>';
    }
    if (category === 'audio') {
      return '<audio controls><source src="' + escapeHtml(url) + '" type="' + escapeHtml(type) + '">Your browser does not support the audio tag.</audio>';
    }
    return '<div class="media-sheet-nopreview">' + icon('content', { size: 26 }) +
      '<p>' + escapeHtml(mdT('media_js_no_preview', 'No preview for this file type.')) + '</p></div>';
  }

  function mediaDownloadUrl(mediaId) {
    if (!mediaId) {
      return '';
    }
    return ADMIN_MEDIA_API() + '?action=download&id=' + encodeURIComponent(String(mediaId));
  }

  function closeMediaModal() {
    closeModal('media-modal');
    state.currentMediaItem = null;
  }

  function downloadCurrentMedia() {
    if (!state.currentMediaItem || !state.currentMediaItem.id) {
      showError(mdT('media_js_no_item_selected', 'No media item selected.'));
      return;
    }
    const link = document.createElement('a');
    link.href = mediaDownloadUrl(state.currentMediaItem.id);
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    link.remove();
  }

  function triggerReplaceMediaFile() {
    if (!state.currentMediaItem?.id) {
      showError(mdT('media_js_no_item_selected', 'No media item selected.'));
      return;
    }
    document.getElementById('media-replace-input')?.click();
  }

  async function handleReplaceMediaFileSelected(event) {
    const input = event.target;
    const file = input?.files?.[0];
    input.value = '';
    if (!file || !state.currentMediaItem?.id) {
      return;
    }
    if (!(await BirklyFeedback.confirm(mdT('media_js_confirm_replace', 'Replace the original file for this media item? References keep the same ID.')))) {
      return;
    }
    try {
      setUploadBusy(true, mdT('media_js_replacing', 'Replacing file…'));
      const formData = new FormData();
      formData.append('id', state.currentMediaItem.id);
      formData.append('file', file);
      formData.append('_csrf', getCSRFToken());
      const response = await fetch(ADMIN_MEDIA_API() + '?action=replace', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const result = await parseMediaApiResponse(response);
      if (result.status !== 'ok') {
        showError(getResultMessage(result, mdT('media_js_replace_failed', 'Replace failed.')));
        return;
      }
      const updated = normalizeMediaItem(result.data?.media || result.data || {});
      state.currentMediaItem = updated;
      state.currentMedia = state.currentMedia.map(function (entry) {
        return entry.id === updated.id ? { ...entry, ...updated } : entry;
      });
      renderMediaModalBody(updated);
      updateMediaSheetActions(updated);
      renderMediaCollection(state.currentMedia);
      showSuccess(getResultMessage(result, mdT('media_js_replace_ok', 'File replaced successfully.')));
    } catch (error) {
      showError(error.message || mdT('media_js_replace_failed', 'Replace failed.'));
    } finally {
      setUploadBusy(false);
    }
  }

  async function createWebVariantForCurrent() {
    if (!state.currentMediaItem?.id || !isRasterImageItem(state.currentMediaItem)) {
      return;
    }
    try {
      const formData = new FormData();
      formData.append('id', state.currentMediaItem.id);
      formData.append('variant', 'web');
      formData.append('_csrf', getCSRFToken());
      const response = await fetch(ADMIN_MEDIA_API() + '?action=create_variant', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const result = await response.json();
      if (result.status !== 'ok') {
        showError(getResultMessage(result, mdT('media_js_variant_failed', 'Could not create web variant.')));
        return;
      }
      const updated = normalizeMediaItem(result.data?.media || state.currentMediaItem);
      state.currentMediaItem = updated;
      renderMediaModalBody(updated);
      showSuccess(getResultMessage(result, mdT('media_js_variant_ok', 'Web-optimized variant created.')));
    } catch (error) {
      showError(error.message || mdT('media_js_variant_failed', 'Could not create web variant.'));
    }
  }

  async function bakeCropVariantForCurrent() {
    if (!state.currentMediaItem?.id || !isRasterImageItem(state.currentMediaItem)) {
      return;
    }
    if (!hasPartialCropRect(readFramingFromDom().crop)) {
      showError(mdT('media_js_crop_bake_need_region', 'Adjust the crop box before baking.'));
      return;
    }
    try {
      await saveCurrentMediaDetails(true);
      const formData = new FormData();
      formData.append('id', state.currentMediaItem.id);
      formData.append('variant', 'crop');
      formData.append('_csrf', getCSRFToken());
      const response = await fetch(ADMIN_MEDIA_API() + '?action=create_variant', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
      const result = await response.json();
      if (result.status !== 'ok') {
        showError(getResultMessage(result, mdT('media_js_crop_bake_failed', 'Could not bake crop variant.')));
        return;
      }
      const updated = normalizeMediaItem(result.data?.media || state.currentMediaItem);
      state.currentMediaItem = updated;
      renderMediaModalBody(updated);
      updateMediaSheetActions(updated);
      showSuccess(getResultMessage(result, mdT('media_js_crop_bake_ok', 'Crop baked to variant file.')));
    } catch (error) {
      showError(error.message || mdT('media_js_crop_bake_failed', 'Could not bake crop variant.'));
    }
  }

  async function saveCurrentMediaDetails(skipSuccessToast) {
    if (!state.currentMediaItem) {
      showError(mdT('media_js_no_item_selected', 'No media item selected.'));
      return;
    }

    const altText = (document.getElementById('media-alt-text')?.value || '').trim();
    const focalX = Number(document.getElementById('media-focal-x')?.value || 50);
    const focalY = Number(document.getElementById('media-focal-y')?.value || 50);
    const cropX = Number(document.getElementById('media-crop-x')?.value || 0);
    const cropY = Number(document.getElementById('media-crop-y')?.value || 0);
    const cropW = Number(document.getElementById('media-crop-w')?.value || 100);
    const cropH = Number(document.getElementById('media-crop-h')?.value || 100);

    try {
      const formData = new FormData();
      formData.append('id', state.currentMediaItem.id);
      formData.append('alt_text', altText);
      formData.append('focal_x', String(Math.max(0, Math.min(100, Math.round(focalX)))));
      formData.append('focal_y', String(Math.max(0, Math.min(100, Math.round(focalY)))));
      formData.append('crop_x', String(Math.max(0, Math.min(100, Math.round(cropX)))));
      formData.append('crop_y', String(Math.max(0, Math.min(100, Math.round(cropY)))));
      formData.append('crop_w', String(Math.max(1, Math.min(100, Math.round(cropW)))));
      formData.append('crop_h', String(Math.max(1, Math.min(100, Math.round(cropH)))));
      formData.append('_csrf', getCSRFToken());

      const response = await fetch(ADMIN_MEDIA_API() + '?action=update', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });

      const result = await response.json();
      if (result.status === 'ok') {
        const updatedMedia = normalizeMediaItem(result.data?.media || {
          ...state.currentMediaItem,
          alt_text: altText,
          focal_point: { x: focalX, y: focalY },
          crop_rect: result.data?.media?.crop_rect ?? null,
          variants: result.data?.media?.variants ?? state.currentMediaItem.variants,
        });

        state.currentMedia = state.currentMedia.map(function (item) {
          return item.id === updatedMedia.id ? normalizeMediaItem({ ...item, ...updatedMedia }) : item;
        });
        state.currentMediaItem = normalizeMediaItem({ ...state.currentMediaItem, ...updatedMedia });
        renderMediaCollection(getFilteredMedia());
        renderMediaModalBody(state.currentMediaItem);
        if (!skipSuccessToast) {
          showSuccess(mdT('media_js_media_details_saved', 'Media details saved successfully.'));
        }
      } else {
        showError(mdT('media_js_err_save_details', 'Failed to save media details:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));
      }
    } catch (error) {
      showError(mdT('media_js_err_save_details_generic', 'Error saving media details:') + ' ' + error.message);
    }
  }

  function openMoveModal(ids) {
    const moveIds = Array.isArray(ids) && ids.length
      ? ids
      : (state.currentMediaItem ? [state.currentMediaItem.id] : []);

    if (!moveIds.length) {
      showError(mdT('media_js_select_items_move', 'Select at least one media item to move.'));
      return;
    }

    state.pendingMoveIds = moveIds;
    state.selectedMoveFolder = state.currentMediaItem?.folder || '';
    renderMoveFolderTree();
    openModal('move-modal');
  }

  function closeMoveModal() {
    closeModal('move-modal');
    state.pendingMoveIds = [];
    state.selectedMoveFolder = '';
  }

  async function moveMediaItem(id, folder) {
    try {
      const formData = new FormData();
      formData.append('id', id);
      formData.append('folder', folder || '');
      formData.append('_csrf', getCSRFToken());

      const response = await fetch(ADMIN_MEDIA_API() + '?action=move_to_folder', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });

      return await response.json();
    } catch (error) {
      return { status: 'error', message: error.message };
    }
  }

  async function confirmMove() {
    if (!state.pendingMoveIds.length) {
      showError(mdT('media_js_no_media_move', 'No media selected to move.'));
      return;
    }

    const moveCount = state.pendingMoveIds.length;
    const failures = [];
    for (let index = 0; index < moveCount; index += 1) {
      const result = await moveMediaItem(state.pendingMoveIds[index], state.selectedMoveFolder);
      if (result.status !== 'ok') {
        failures.push(state.pendingMoveIds[index] + ': ' + getResultMessage(result, 'Move failed'));
      }
    }

    closeMoveModal();
    closeMediaModal();
    clearSelection();
    await Promise.all([loadFolders(), loadMedia()]);

    if (failures.length) {
      showError(failures.join('\n'));
    } else {
      showSuccess(moveCount === 1 ? 'Media moved successfully.' : 'Selected media moved successfully.');
    }
  }

  function toggleMediaSelection(id, checked) {
    if (checked) {
      state.selectedIds.add(id);
    } else {
      state.selectedIds.delete(id);
    }
    syncSelectionUi();
    updateSelectionToolbar();
  }

  function pruneSelection() {
    const validIds = new Set(state.currentMedia.map(function (item) {
      return item.id;
    }));

    state.selectedIds.forEach(function (id) {
      if (!validIds.has(id)) {
        state.selectedIds.delete(id);
      }
    });
  }

  function syncSelectionUi() {
    document.querySelectorAll('.media-card').forEach(function (card) {
      const id = card.dataset.mediaId;
      const selected = state.selectedIds.has(id);
      card.classList.toggle('is-selected', selected);
      const checkbox = card.querySelector('.media-select-checkbox');
      if (checkbox) {
        checkbox.checked = selected;
      }
    });
  }

  function updateSelectionToolbar() {
    const toolbar = document.getElementById('media-bulk-toolbar');
    const count = document.getElementById('media-selected-count');
    const selectedCount = state.selectedIds.size;

    if (count) {
      count.textContent = String(selectedCount);
    }
    if (toolbar) {
      toolbar.hidden = selectedCount === 0;
    }
    document.getElementById('media-grid')?.classList.toggle('has-selection', selectedCount > 0);
    const pageBar = document.getElementById('media-page-bar');
    if (pageBar) {
      pageBar.hidden = selectedCount > 0;
    }
  }

  function clearSelection() {
    state.selectedIds.clear();
    syncSelectionUi();
    updateSelectionToolbar();
  }

  async function deleteMedia(id) {
    try {
      const formData = new FormData();
      formData.append('id', id);
      formData.append('_csrf', getCSRFToken());

      const response = await fetch(ADMIN_MEDIA_API() + '?action=delete', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });

      return await response.json();
    } catch (error) {
      return { status: 'error', message: error.message };
    }
  }

  async function deleteSelectedMedia() {
    const ids = Array.from(state.selectedIds);
    if (!ids.length) {
      showError(mdT('media_js_no_media_selected', 'No media selected.'));
      return;
    }
    if (!(await BirklyFeedback.confirm(mdT('media_js_confirm_delete_selected', 'Delete the selected media items?')))) {
      return;
    }

    const failures = [];
    for (let index = 0; index < ids.length; index += 1) {
      const result = await deleteMedia(ids[index]);
      if (result.status !== 'ok') {
        failures.push(ids[index] + ': ' + getResultMessage(result, 'Delete failed'));
      }
    }

    clearSelection();
    await Promise.all([loadFolders(), loadMedia()]);

    if (failures.length) {
      showError(failures.join('\n'));
    } else {
      showSuccess(mdT('media_js_selected_media_deleted_success', 'Selected media deleted successfully.'));
    }
  }

  async function deleteCurrentMedia() {
    if (!state.currentMediaItem) {
      showError(mdT('media_js_no_item_selected', 'No media item selected.'));
      return;
    }
    if (!(await BirklyFeedback.confirm(mdT('media_js_confirm_delete_file', 'Delete this media file?')))) {
      return;
    }

    const mediaId = state.currentMediaItem.id;
    const result = await deleteMedia(mediaId);
    if (result.status === 'ok') {
      closeMediaModal();
      state.selectedIds.delete(mediaId);
      await Promise.all([loadFolders(), loadMedia()]);
      showSuccess(mdT('media_js_media_deleted_success', 'Media deleted successfully.'));
    } else {
      showError(mdT('media_js_err_delete_media', 'Failed to delete media:') + ' ' + getResultMessage(result, mdT('ai_conn_js_unknown_error', 'Unknown error')));
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMediaPage);
  } else {
    initMediaPage();
  }
})();
