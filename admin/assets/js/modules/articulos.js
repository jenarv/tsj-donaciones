/**
 * ARTICULOS MODULE - Updated with Role-Based Access Control
 * Handles articles/items management with department restrictions
 */

const Articulos = {
  data: [],
  currentArticle: null,
  availableCategories: [], // Categories user can access
  userInfo: null, // Store user role and department info

  /**
   * Initialize articulos tab
   */
  async init() {
    await this.loadUserCategories();
    this.renderTab();
    await this.load();
  },

  /**
   * Load categories available to the current user
   */
  async loadUserCategories() {
    try {
      const data = await Utils.apiRequest('/categorias/disponibles.php?tipo=admin');
      if (data.success) {
        this.availableCategories = data.data;
        console.log(`Loaded ${this.availableCategories.length} categories for user`);
      }
    } catch (error) {
      console.error('Error loading categories:', error);
      this.availableCategories = [];
    }
  },

  /**
   * Render tab content
   */
  renderTab() {
    const container = document.getElementById('tab-container');
    
    const content = Utils.createElement('div', {
      id: 'tab-articulos',
      className: 'tab-content'
    });

    // Header with role info
    const header = Utils.createElement('div', {
      className: 'content-header'
    }, [
      Utils.createElement('div', {}, [
        Utils.createElement('h2', {}, ['Gestión de Artículos']),
        this.userInfo ? Utils.createElement('p', {
          style: 'color: #666; font-size: 14px; margin-top: 5px;'
        }, [
          `Rol: ${this.userInfo.rol_tipo === 'Super_Admin' ? 'Super Administrador' : 'Administrador de Departamento'}` +
          (this.userInfo.departamento ? ` - ${this.userInfo.departamento}` : '')
        ]) : null
      ]),
      Utils.createElement('button', {
        className: 'btn-primary',
        onClick: () => this.showAddModal()
      }, ['Agregar Artículo'])
    ]);
    content.appendChild(header);

    // Category filter
    if (this.availableCategories.length > 0) {
      const filterDiv = Utils.createElement('div', {
        className: 'filter-section',
        style: 'margin-bottom: 20px;'
      });
      
      const filterLabel = Utils.createElement('label', {
        style: 'margin-right: 10px; font-weight: 500;'
      }, ['Filtrar por categoría:']);
      
      const filterSelect = Utils.createElement('select', {
        id: 'category-filter',
        className: 'filter-select',
        onChange: () => this.filterByCategory()
      });
      
      // Add "All" option
      filterSelect.appendChild(Utils.createElement('option', { value: '' }, ['Todas las categorías']));
      
      // Add category options
      this.availableCategories.forEach(cat => {
        filterSelect.appendChild(Utils.createElement('option', {
          value: cat.id_categoria
        }, [`${cat.nombre_categoria} (${cat.tipo_acceso})`]));
      });
      
      filterDiv.appendChild(filterLabel);
      filterDiv.appendChild(filterSelect);
      content.appendChild(filterDiv);
    }

    // List container
    const listContainer = Utils.createElement('div', {
      id: 'articulos-list',
      className: 'table-container'
    });
    content.appendChild(listContainer);

    container.appendChild(content);

    // Create modals
    this._createAddModal();
    this._createEditModal();
  },

  /**
   * Filter articles by category
   */
  filterByCategory() {
    const selectedCategoryId = document.getElementById('category-filter')?.value;
    
    if (!selectedCategoryId) {
      this.render();
      return;
    }
    
    const filteredData = this.data.filter(art => 
      art.id_categoria == selectedCategoryId
    );
    
    this.render(filteredData);
  },

  /**
   * Load articulos
   */
  async load() {
    const listContainer = document.getElementById('articulos-list');
    Utils.showLoading(listContainer, 'Cargando artículos...');

    try {
      const data = await Utils.apiRequest('/articulos/todos.php');

      if (!data.success) {
        throw new Error(data.error);
      }

      this.data = data.data;
      this.userInfo = data.user_info || null;
      
      console.log(`Loaded ${this.data.length} articles for user`);
      this.render();

    } catch (error) {
      console.error('Error:', error);
      Utils.showEmptyState(listContainer, 'Error al cargar artículos: ' + error.message);
    }
  },

  /**
   * Render articulos table
   */
  render(dataToRender = null) {
    const renderData = dataToRender || this.data;
    
    const columns = [
      { key: 'id_paquete', label: 'ID' },
      { 
        key: 'imagen_url', 
        label: 'Imagen',
        render: (value, row) => Table.renderers.imageThumbnail(value, row.nombre)
      },
      { key: 'nombre', label: 'Nombre' },
      { 
        key: 'nombre_categoria', 
        label: 'Categoría',
        render: (value, row) => {
          const badge = row.tipo_acceso === 'Universal' 
            ? '<span style="background: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">Universal</span>'
            : '<span style="background: #2196F3; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">Depto</span>';
          return value + badge;
        }
      },
      { 
        key: 'precio_estimado', 
        label: 'Precio',
        render: (value) => Table.renderers.currency(value)
      },
      { 
        key: 'solicitudes_activas', 
        label: 'Solicitudes',
        render: (value) => value || 0
      },
      { 
        key: 'solicitudes_activas', 
        label: 'Estado',
        render: (value) => value > 0 
          ? '<span class="status-badge status-Reservado">Reservado</span>' 
          : '<span class="status-badge status-Aprobado">Disponible</span>'
      }
    ];

    Table.render('articulos-list', columns, renderData, {
      clickable: true,
      onRowClick: (row) => this.showEditModal(row.id_paquete),
      emptyMessage: 'No hay artículos disponibles en las categorías a las que tienes acceso'
    });
  },

    /**
   * Create add modal
   */
  _createAddModal() {
    const form = Utils.createElement('form', {
      id: 'form-add-article'
    });

    // Build category options from available categories
    const categoryOptions = this.availableCategories.map(cat => ({
      value: cat.id_categoria,
      label: `${cat.nombre_categoria} (${cat.tipo_acceso})`
    }));

    // Add form fields
    const fields = [
      { 
        type: 'text', 
        id: 'new-id-paquete', 
        label: 'ID del Paquete', 
        required: true, 
        placeholder: 'Ej: ISIC-025'
      },
      { 
        type: 'select', 
        id: 'new-id-categoria', 
        label: 'Categoría', 
        required: true, 
        placeholder: 'Seleccionar categoría...', 
        options: categoryOptions
      },
      { 
        type: 'text', 
        id: 'new-nombre', 
        label: 'Nombre del Artículo', 
        required: true 
      },
      { 
        type: 'textarea', 
        id: 'new-descripcion', 
        label: 'Descripción', 
        rows: 3 
      },
      { 
        type: 'number', 
        id: 'new-precio', 
        label: 'Precio Estimado', 
        step: '0.01', 
        value: '1000.00' 
      },
      { 
        type: 'url', 
        id: 'new-enlace', 
        label: 'Enlace de Referencia', 
        placeholder: 'https://...' 
      }
    ];

    fields.forEach(field => {
      const formGroup = this._createFormField(field);
      form.appendChild(formGroup);
    });

    // Add image upload section
    const imageUpload = ImageUpload.createUploadUI();
    form.appendChild(imageUpload);

    // Add form actions
    const actions = Utils.createElement('div', {
      className: 'form-actions'
    }, [
      Utils.createElement('button', {
        type: 'button',
        className: 'btn-secondary',
        onClick: () => Modal.close('modal-add-article')
      }, ['Cancelar']),
      Utils.createElement('button', {
        type: 'submit',
        className: 'btn-primary'
      }, ['Guardar Artículo'])
    ]);
    form.appendChild(actions);

    // Handle submit - CAMBIO AQUÍ: usar arrow function
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.add();  // Ahora 'this' apunta correctamente a Articulos
    });

    Modal.create('modal-add-article', 'Agregar Nuevo Artículo', form);
  },

  /**
   * Create edit modal
   */
  _createEditModal() {
    const form = Utils.createElement('form', {
      id: 'form-edit-article'
    });

    const categoryOptions = this.availableCategories.map(cat => ({
      value: cat.id_categoria,
      label: `${cat.nombre_categoria} (${cat.tipo_acceso})`
    }));

    // Add form fields
    const fields = [
      { 
        type: 'text', 
        id: 'edit-id-paquete', 
        label: 'ID del Paquete', 
        required: true, 
        readonly: true 
      },
      { 
        type: 'select', 
        id: 'edit-id-categoria', 
        label: 'Categoría', 
        required: true, 
        options: categoryOptions
      },
      { 
        type: 'text', 
        id: 'edit-nombre', 
        label: 'Nombre del Artículo', 
        required: true 
      },
      { 
        type: 'textarea', 
        id: 'edit-descripcion', 
        label: 'Descripción', 
        rows: 3 
      },
      { 
        type: 'number', 
        id: 'edit-precio', 
        label: 'Precio Estimado', 
        step: '0.01' 
      },
      { 
        type: 'url', 
        id: 'edit-enlace', 
        label: 'Enlace de Referencia', 
        placeholder: 'https://...' 
      }
    ];

    fields.forEach(field => {
      const formGroup = this._createFormField(field);
      form.appendChild(formGroup);
    });

    // Add image upload section with current image display
    const imageContainer = Utils.createElement('div', {
      className: 'image-upload-container'
    });

    const label = Utils.createElement('label', {}, ['Imagen del Artículo']);
    imageContainer.appendChild(label);

    // Current image display
    const currentImageDiv = Utils.createElement('div', {
      id: 'edit-current-image',
      style: 'display: none; margin-bottom: 15px'
    }, [
      Utils.createElement('p', {
        style: 'margin-bottom: 10px; color: #666; font-size: 14px'
      }, ['Imagen actual:']),
      Utils.createElement('img', {
        id: 'edit-current-image-preview',
        className: 'image-preview',
        alt: 'Current image',
        style: 'max-width: 300px'
      }),
      // Botón para eliminar imagen actual
      Utils.createElement('button', {
        type: 'button',
        id: 'btn-delete-current-image',
        className: 'btn-remove-image',
        style: 'margin-top: 10px',
        onClick: () => this.markImageForDeletion()
      }, ['Eliminar Imagen Actual'])
    ]);
    imageContainer.appendChild(currentImageDiv);

    // Hidden input para marcar si se debe eliminar la imagen
    const deleteImageInput = Utils.createElement('input', {
      type: 'hidden',
      id: 'delete-current-image',
      value: '0'
    });
    imageContainer.appendChild(deleteImageInput);

    // New image upload
    const editImageUpload = ImageUpload.createUploadUI('edit-');
    imageContainer.appendChild(editImageUpload);

    form.appendChild(imageContainer);

    // Add form actions
    const actions = Utils.createElement('div', {
      className: 'form-actions'
    }, [
      Utils.createElement('button', {
        type: 'button',
        className: 'btn-secondary',
        onClick: () => Modal.close('modal-edit-article')
      }, ['Cancelar']),
      Utils.createElement('button', {
        type: 'submit',
        className: 'btn-primary'
      }, ['Guardar Cambios'])
    ]);
    form.appendChild(actions);

    // Handle submit
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.update();
    });

    Modal.create('modal-edit-article', 'Editar Artículo', form);
  },

  /**
   * Create form field helper
   */
  _createFormField(field) {
    const formGroup = Utils.createElement('div', {
      className: 'form-group'
    });

    // Label
    const label = Utils.createElement('label', {
      for: field.id
    }, [field.label + (field.required ? ' *' : '')]);
    formGroup.appendChild(label);

    // Input
    let input;
    if (field.type === 'textarea') {
      input = Utils.createElement('textarea', {
        id: field.id,
        name: field.id,
        rows: field.rows || 3,
        placeholder: field.placeholder || ''
      });
    } else if (field.type === 'select') {
      input = Utils.createElement('select', {
        id: field.id,
        name: field.id,
        required: field.required || false
      });

      if (field.placeholder) {
        const placeholderOption = Utils.createElement('option', {
          value: ''
        }, [field.placeholder]);
        input.appendChild(placeholderOption);
      }

      if (field.options) {
        field.options.forEach(opt => {
          const option = Utils.createElement('option', {
            value: opt.value
          }, [opt.label]);
          input.appendChild(option);
        });
      }
    } else {
      input = Utils.createElement('input', {
        type: field.type || 'text',
        id: field.id,
        name: field.id,
        placeholder: field.placeholder || '',
        required: field.required || false,
        value: field.value || '',
        step: field.step || undefined,
        readonly: field.readonly || false
      });

      if (field.readonly) {
        input.style.backgroundColor = '#f5f5f5';
        input.style.cursor = 'not-allowed';
      }
    }

    formGroup.appendChild(input);
    return formGroup;
  },

  /**
   * Show add modal
   */
  showAddModal() {
    // Reset form
    const form = document.getElementById('form-add-article');
    if (form) form.reset();

    // Reset image upload
    ImageUpload.reset();
    ImageUpload.remove('image-dropzone', 'image-preview-container', 'image-file-input');

    // Setup image upload
    ImageUpload.setup('image-dropzone', 'image-file-input', 'image-preview-container', 'image-preview');

    Modal.open('modal-add-article');
  },

  /**
   * Show edit modal
   */
  async showEditModal(idPaquete) {
  try {
    // Load article details
    const data = await Utils.apiRequest(`/admin/detalle.php?id=${idPaquete}`);

    if (!data.success) {
      throw new Error(data.error);
    }

    this.currentArticle = data.data;

    // Populate form
    document.getElementById('edit-id-paquete').value = this.currentArticle.id_paquete;
    document.getElementById('edit-id-categoria').value = this.currentArticle.id_categoria;
    document.getElementById('edit-nombre').value = this.currentArticle.nombre;
    document.getElementById('edit-descripcion').value = this.currentArticle.descripcion || '';
    document.getElementById('edit-precio').value = this.currentArticle.precio_estimado;
    document.getElementById('edit-enlace').value = this.currentArticle.enlace_referencia || '';

    // Reset delete flag
    document.getElementById('delete-current-image').value = '0';

    // Reset new image selection
    ImageUpload.reset();

    // Show current image
    const currentImageDiv = document.getElementById('edit-current-image');
    const currentImagePreview = document.getElementById('edit-current-image-preview');

    if (this.currentArticle.imagen_url) {
      currentImagePreview.src = this.currentArticle.imagen_url;
      currentImagePreview.onerror = function() {
        currentImageDiv.innerHTML = `
          <p style="margin-bottom: 10px; color: #666; font-size: 14px;">Imagen actual:</p>
          <div class="image-placeholder">
            <div class="image-placeholder-icon">📦</div>
            <p class="image-placeholder-text">Imagen no disponible</p>
          </div>
        `;
      };
      currentImageDiv.style.display = 'block';
    } else {
      currentImageDiv.style.display = 'none';
    }

    // Reset new image preview
    ImageUpload.remove('edit-image-dropzone', 'edit-image-preview-container', 'edit-image-file-input');

    // Setup image upload
    ImageUpload.setup('edit-image-dropzone', 'edit-image-file-input', 'edit-image-preview-container', 'edit-image-preview');

    Modal.open('modal-edit-article');

  } catch (error) {
    console.error('Error:', error);
    Utils.notify('Error al cargar el artículo: ' + error.message, 'error');
  }
},

  /**
   * Mark current image for deletion
   */
  markImageForDeletion() {
    const currentImageDiv = document.getElementById('edit-current-image');
    const deleteInput = document.getElementById('delete-current-image');
    const deleteBtn = document.getElementById('btn-delete-current-image');
    
    if (confirm('¿Estás seguro de que deseas eliminar la imagen actual?')) {
      // Mark for deletion
      deleteInput.value = '1';
      
      // Update UI
      currentImageDiv.innerHTML = `
        <p style="color: #d32f2f; font-weight: 500; margin: 10px 0;">
          ⚠️ La imagen actual será eliminada al guardar los cambios
        </p>
        <button type="button" class="btn-secondary" style="margin-top: 10px;" onclick="Articulos.undoImageDeletion()">
          Cancelar eliminación
        </button>
      `;
    }
  },

  /**
   * Undo image deletion
   */
  undoImageDeletion() {
    const deleteInput = document.getElementById('delete-current-image');
    deleteInput.value = '0';
    
    // Restore original image display
    const currentImageDiv = document.getElementById('edit-current-image');
    const currentImagePreview = document.getElementById('edit-current-image-preview');
    
    if (this.currentArticle.imagen_url) {
      currentImageDiv.innerHTML = `
        <p style="margin-bottom: 10px; color: #666; font-size: 14px;">Imagen actual:</p>
        <img id="edit-current-image-preview" class="image-preview" alt="Current image" style="max-width: 300px" src="${this.currentArticle.imagen_url}">
        <button type="button" id="btn-delete-current-image" class="btn-remove-image" style="margin-top: 10px;">
          Eliminar Imagen Actual
        </button>
      `;
      
      // Re-attach event listener
      document.getElementById('btn-delete-current-image').addEventListener('click', () => {
        this.markImageForDeletion();
      });
    }
  },
    /**
   * Add new article
   */
  async add() {
    const formData = new FormData();

    formData.append('id_paquete', document.getElementById('new-id-paquete').value.trim());
    formData.append('id_categoria', document.getElementById('new-id-categoria').value);
    formData.append('nombre', document.getElementById('new-nombre').value.trim());
    formData.append('descripcion', document.getElementById('new-descripcion').value.trim());
    formData.append('precio_estimado', document.getElementById('new-precio').value);
    formData.append('enlace_referencia', document.getElementById('new-enlace').value.trim());

    // Add image if selected
    const imageFile = ImageUpload.getFile();
    if (imageFile) {
      formData.append('imagen', imageFile);
    }

    try {
      const response = await fetch(`${CONFIG.API_BASE_URL}/admin/agregar-articulo.php`, {
        method: 'POST',
        credentials: 'include',
        body: formData
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error);
      }

      Utils.notify('Artículo agregado exitosamente', 'success');
      Modal.close('modal-add-article');
      ImageUpload.reset();
      await this.load();

    } catch (error) {
      console.error('Error:', error);
      Utils.notify('Error al agregar artículo: ' + error.message, 'error');
    }
  },

  /**
 * Update article
 */
  async update() {
    const formData = new FormData();

    formData.append('id_paquete', document.getElementById('edit-id-paquete').value.trim());
    formData.append('id_categoria', document.getElementById('edit-id-categoria').value);
    formData.append('nombre', document.getElementById('edit-nombre').value.trim());
    formData.append('descripcion', document.getElementById('edit-descripcion').value.trim());
    formData.append('precio_estimado', document.getElementById('edit-precio').value);
    formData.append('enlace_referencia', document.getElementById('edit-enlace').value.trim());
    formData.append('imagen_url_actual', this.currentArticle?.imagen_url || '');
    
    // Check if user wants to delete current image
    const deleteCurrentImage = document.getElementById('delete-current-image').value === '1';
    formData.append('delete_image', deleteCurrentImage ? '1' : '0');

    // Add new image if selected
    const imageFile = ImageUpload.getFile();
    if (imageFile) {
      formData.append('imagen', imageFile);
    }

    try {
      const response = await fetch(`${CONFIG.API_BASE_URL}/admin/actualizar-articulo.php`, {
        method: 'POST',
        credentials: 'include',
        body: formData
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error);
      }

      Utils.notify('Artículo actualizado exitosamente', 'success');
      Modal.close('modal-edit-article');
      ImageUpload.reset();
      this.currentArticle = null;
      await this.load();

    } catch (error) {
      console.error('Error:', error);
      Utils.notify('Error al actualizar artículo: ' + error.message, 'error');
    }
  }
};

// Make Articulos available globally
window.Articulos = Articulos;
