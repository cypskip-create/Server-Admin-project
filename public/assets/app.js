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
  let previewUrl = null;

  if (uploadForm && photoInput && previewFrame && previewStatus) {
    photoInput.addEventListener('change', () => {
      const file = photoInput.files?.[0];
      if (!file) return;
      const accepted = ['image/jpeg', 'image/png', 'image/webp'];
      if (!accepted.includes(file.type)) {
        photoInput.value = '';
        previewStatus.textContent = 'Choose a JPEG, PNG or WebP image.';
        previewStatus.className = 'photo-preview-status error-text';
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        photoInput.value = '';
        previewStatus.textContent = 'Choose an image no larger than 2 MB.';
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
    });

    uploadForm.addEventListener('submit', (event) => {
      if (!photoInput.files?.length) {
        event.preventDefault();
        previewStatus.textContent = 'Choose a photo before continuing.';
        previewStatus.className = 'photo-preview-status error-text';
        photoInput.focus();
        return;
      }
      if (uploadButton) {
        uploadButton.disabled = true;
        uploadButton.textContent = 'Uploading…';
      }
      previewStatus.textContent = 'Uploading and securing your photo…';
    });

    window.addEventListener('pagehide', () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    }, { once: true });
  }
})();
