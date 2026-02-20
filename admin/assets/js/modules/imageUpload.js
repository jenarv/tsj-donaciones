/**
 * IMAGE UPLOAD MODULE
 * Handles image upload functionality with drag & drop
 */

const ImageUpload = {
  selectedFile: null,

  /**
   * Setup image upload for a form
   */
  setup(dropzoneId, fileInputId, previewContainerId, previewImageId) {
    const dropzone = document.getElementById(dropzoneId);
    const fileInput = document.getElementById(fileInputId);
    const previewContainer = document.getElementById(previewContainerId);
    const previewImage = document.getElementById(previewImageId);

    if (!dropzone || !fileInput) return;

    // Click to select file
    dropzone.addEventListener('click', () => {
      fileInput.click();
    });

    // File input change
    fileInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        this.handleFile(file, dropzone, previewContainer, previewImage);
      }
    });

    // Drag & Drop events
    dropzone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropzone.classList.add('dragover');
    });

    dropzone.addEventListener('dragleave', () => {
      dropzone.classList.remove('dragover');
    });

    dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropzone.classList.remove('dragover');

      const file = e.dataTransfer.files[0];
      if (file) {
        this.handleFile(file, dropzone, previewContainer, previewImage);
      }
    });
  },

  /**
   * Handle selected file
   */
  handleFile(file, dropzone, previewContainer, previewImage) {
    // Validate file
    const validation = Utils.validateImageFile(file);
    if (!validation.valid) {
      Utils.notify(validation.error, 'error');
      return;
    }

    this.selectedFile = file;

    // Show preview
    Utils.createImagePreview(file, (dataUrl) => {
      previewImage.src = dataUrl;
      previewContainer.style.display = 'block';
      dropzone.style.display = 'none';
    });
  },

  /**
   * Remove selected image
   */
  remove(dropzoneId, previewContainerId, fileInputId) {
    this.selectedFile = null;

    const previewContainer = document.getElementById(previewContainerId);
    const dropzone = document.getElementById(dropzoneId);
    const fileInput = document.getElementById(fileInputId);

    if (previewContainer) previewContainer.style.display = 'none';
    if (dropzone) dropzone.style.display = 'block';
    if (fileInput) fileInput.value = '';
  },

  /**
   * Change selected image
   */
  change(fileInputId) {
    const fileInput = document.getElementById(fileInputId);
    if (fileInput) fileInput.click();
  },

  /**
   * Get selected file
   */
  getFile() {
    return this.selectedFile;
  },

  /**
   * Reset
   */
  reset() {
    this.selectedFile = null;
  },

  /**
   * Create image upload UI
   */
  createUploadUI(prefix = '') {
    const container = Utils.createElement('div', {
      className: 'image-upload-container'
    });

    const label = Utils.createElement('label', {}, ['Imagen del Artículo']);
    container.appendChild(label);

    // Dropzone
    const dropzone = Utils.createElement('div', {
      id: `${prefix}image-dropzone`,
      className: 'image-dropzone'
    }, [
      Utils.createElement('div', {
        className: 'dropzone-icon'
      }, ['📷']),
      Utils.createElement('p', {
        className: 'dropzone-text'
      }, [
        Utils.createElement('strong', {}, ['Arrastra una imagen aquí']),
        ' o haz clic para seleccionar'
      ]),
      Utils.createElement('p', {
        className: 'dropzone-hint'
      }, ['Formatos: JPG, PNG, GIF (máx. 5MB)'])
    ]);
    container.appendChild(dropzone);

    // Hidden file input
    const fileInput = Utils.createElement('input', {
      type: 'file',
      id: `${prefix}image-file-input`,
      accept: 'image/*',
      style: 'display: none'
    });
    container.appendChild(fileInput);

    // Preview container
    const previewContainer = Utils.createElement('div', {
      id: `${prefix}image-preview-container`,
      className: 'image-preview-container',
      style: 'display: none'
    });

    const previewImage = Utils.createElement('img', {
      id: `${prefix}image-preview`,
      className: 'image-preview',
      alt: 'Preview'
    });
    previewContainer.appendChild(previewImage);

    const imageActions = Utils.createElement('div', {
      className: 'image-actions'
    }, [
      Utils.createElement('button', {
        type: 'button',
        className: 'btn-change-image',
        onClick: () => this.change(`${prefix}image-file-input`)
      }, ['Cambiar Imagen']),
      Utils.createElement('button', {
        type: 'button',
        className: 'btn-remove-image',
        onClick: () => this.remove(
          `${prefix}image-dropzone`,
          `${prefix}image-preview-container`,
          `${prefix}image-file-input`
        )
      }, ['Eliminar Imagen'])
    ]);
    previewContainer.appendChild(imageActions);

    container.appendChild(previewContainer);

    return container;
  }
};

// Make ImageUpload available globally
window.ImageUpload = ImageUpload;
