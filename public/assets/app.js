(() => {
  const header = document.querySelector('.site-header');
  const toggle = document.querySelector('.menu-toggle');
  const navigation = document.querySelector('#primary-nav');

  if (header && toggle && navigation) {
    const setMenuOpen = (open, returnFocus = false) => {
      header.classList.toggle('menu-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
      if (returnFocus) toggle.focus();
    };

    toggle.addEventListener('click', () => {
      setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    navigation.querySelectorAll('a, button').forEach((item) => {
      item.addEventListener('click', () => setMenuOpen(false));
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setMenuOpen(false, true);
      }
    });
    document.addEventListener('pointerdown', (event) => {
      if (!header.contains(event.target)) setMenuOpen(false);
    });
    const desktop = window.matchMedia('(min-width: 861px)');
    const resetMenu = () => setMenuOpen(false);
    if (desktop.addEventListener) desktop.addEventListener('change', resetMenu);
    else desktop.addListener?.(resetMenu);
  }

  const uploadForm = document.querySelector('#photo-upload-form');
  const photoInput = document.querySelector('#profile-photo-input');
  const previewFrame = document.querySelector('#photo-preview-frame');
  const previewStatus = document.querySelector('#photo-preview-status');
  const uploadButton = document.querySelector('#photo-upload-button');
  const pickerButton = document.querySelector('.photo-picker-button');
  const initialAvatar = previewFrame?.firstElementChild?.cloneNode(true) || null;
  let previewUrl = null;

  if (uploadForm && photoInput && previewFrame && previewStatus && uploadButton) {
    const clearPreview = () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = null;
      if (initialAvatar) previewFrame.replaceChildren(initialAvatar.cloneNode(true));
      uploadButton.hidden = true;
      uploadButton.disabled = true;
    };

    // Clearing before the native picker opens makes selecting the same file
    // again fire a fresh change event. A cancelled picker leaves the saved
    // server avatar visible and no stale preview ready to submit.
    photoInput.addEventListener('click', () => {
      if (photoInput.value) {
        photoInput.value = '';
        clearPreview();
        previewStatus.textContent = '';
      }
    });

    photoInput.addEventListener('change', () => {
      const file = photoInput.files?.[0];
      if (!file) return;
      const accepted = ['image/jpeg', 'image/png', 'image/webp'];
      if (file.type && !accepted.includes(file.type)) {
        photoInput.value = '';
        clearPreview();
        previewStatus.textContent = 'Choose a JPEG, PNG or WebP image.';
        previewStatus.className = 'photo-preview-status error-text';
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        photoInput.value = '';
        clearPreview();
        previewStatus.textContent = 'Your image is too large. Please choose a photo smaller than 5 MB.';
        previewStatus.className = 'photo-preview-status error-text';
        return;
      }
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = URL.createObjectURL(file);
      let image = previewFrame.querySelector('img');
      if (!image) {
        image = document.createElement('img');
        image.className = 'profile-avatar';
        image.alt = `Selected profile photo preview for ${previewFrame.dataset.initials || 'your account'}`;
        image.width = 96;
        image.height = 96;
        previewFrame.replaceChildren(image);
      }
      image.src = previewUrl;
      image.alt = 'Preview of the selected profile photo';
      previewStatus.textContent = `${file.name} selected. Your photo will be cropped to a square.`;
      previewStatus.className = 'photo-preview-status';
      uploadButton.hidden = false;
      uploadButton.disabled = false;
    });

    uploadForm.addEventListener('submit', (event) => {
      if (!photoInput.files?.length) {
        event.preventDefault();
        previewStatus.textContent = 'Choose a photo before continuing.';
        previewStatus.className = 'photo-preview-status error-text';
        photoInput.focus();
        return;
      }
      uploadButton.disabled = true;
      uploadButton.textContent = 'Uploading…';
      if (pickerButton) pickerButton.setAttribute('aria-disabled', 'true');
      previewStatus.textContent = 'Uploading and securing your photo…';
      previewStatus.className = 'photo-preview-status';
    });

    document.querySelector('#remove-photo-button')?.addEventListener('click', (event) => {
      if (!window.confirm('Remove your current profile photo?')) event.preventDefault();
    });

    window.addEventListener('pagehide', () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    }, { once: true });
  }

  const requestStatus = document.querySelector('#admin-request-status');
  const adminNote = document.querySelector('#admin-note');
  const statusGuidance = document.querySelector('#admin-status-guidance');
  const updateButton = document.querySelector('#admin-update-submit');
  if (requestStatus && adminNote && statusGuidance && updateButton) {
    const currentStatus = requestStatus.dataset.currentStatus;
    const statusLabel = (value) => value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    const updateActionDescription = () => {
      const selectedStatus = requestStatus.value;
      const unchanged = selectedStatus === currentStatus;
      const needsResponse = unchanged || selectedStatus === 'declined';
      adminNote.required = selectedStatus === 'declined';
      updateButton.disabled = needsResponse && !adminNote.value.trim();
      updateButton.textContent = unchanged ? 'Save response only' : 'Update status and notify student';
      if (unchanged) {
        statusGuidance.textContent = adminNote.value.trim()
          ? `The status will remain ${statusLabel(currentStatus)}; only the response will be saved.`
          : `Current status is ${statusLabel(currentStatus)}. Add a response to save without changing status.`;
      } else {
        statusGuidance.textContent = `This will change status from ${statusLabel(currentStatus)} to ${statusLabel(selectedStatus)}. A response is required when declining.`;
      }
    };
    requestStatus.addEventListener('change', updateActionDescription);
    adminNote.addEventListener('input', updateActionDescription);
    updateActionDescription();
  }
})();
