/**
 * MODAL COMPONENT
 * Manages modal creation and interaction
 */

const Modal = {
  /**
   * Create modal HTML structure
   */
  create(id, title, content, options = {}) {
    const modal = Utils.createElement('div', {
      id: id,
      className: 'modal'
    }, [
      Utils.createElement('div', {
        className: 'modal-content'
      }, [
        Utils.createElement('span', {
          className: 'modal-close',
          onClick: () => this.close(id)
        }, ['×']),
        Utils.createElement('h2', {}, [title]),
        content
      ])
    ]);

    // Add to modals container
    const container = document.getElementById('modals-container');
    if (container) {
      container.appendChild(modal);
    } else {
      document.body.appendChild(modal);
    }

    return modal;
  },

  /**
   * Open modal
   */
  open(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
      
      // Add escape key listener
      this._escapeListener = (e) => {
        if (e.key === 'Escape') {
          this.close(modalId);
        }
      };
      document.addEventListener('keydown', this._escapeListener);
      
      // Add click outside listener
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          this.close(modalId);
        }
      });
    }
  },

  /**
   * Close modal
   */
  close(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
      
      // Remove escape key listener
      if (this._escapeListener) {
        document.removeEventListener('keydown', this._escapeListener);
        this._escapeListener = null;
      }
    }
  },

  /**
   * Remove modal from DOM
   */
  destroy(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.remove();
    }
  },

  /**
   * Create form modal
   */
  createFormModal(id, title, fields, onSubmit, options = {}) {
    const form = Utils.createElement('form', {
      id: `form-${id}`
    });

    // Add fields
    fields.forEach(field => {
      const formGroup = this._createFormField(field);
      form.appendChild(formGroup);
    });

    // Add actions
    const actions = Utils.createElement('div', {
      className: 'form-actions'
    }, [
      Utils.createElement('button', {
        type: 'button',
        className: 'btn-secondary',
        onClick: () => this.close(id)
      }, ['Cancelar']),
      Utils.createElement('button', {
        type: 'submit',
        className: 'btn-primary'
      }, [options.submitLabel || 'Guardar'])
    ]);
    form.appendChild(actions);

    // Handle submit
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await onSubmit(new FormData(form));
    });

    return this.create(id, title, form, options);
  },

  /**
   * Create form field
   */
  _createFormField(field) {
    const formGroup = Utils.createElement('div', {
      className: 'form-group'
    });

    // Label
    if (field.label) {
      const label = Utils.createElement('label', {
        for: field.id
      }, [field.label + (field.required ? ' *' : '')]);
      formGroup.appendChild(label);
    }

    // Input
    let input;
    if (field.type === 'textarea') {
      input = Utils.createElement('textarea', {
        id: field.id,
        name: field.name || field.id,
        rows: field.rows || 3,
        placeholder: field.placeholder || '',
        required: field.required || false
      });
    } else if (field.type === 'select') {
      input = Utils.createElement('select', {
        id: field.id,
        name: field.name || field.id,
        required: field.required || false
      });
      
      // Add options
      if (field.options) {
        if (field.placeholder) {
          const placeholderOption = Utils.createElement('option', {
            value: ''
          }, [field.placeholder]);
          input.appendChild(placeholderOption);
        }
        
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
        name: field.name || field.id,
        placeholder: field.placeholder || '',
        required: field.required || false,
        value: field.value || '',
        step: field.step || undefined,
        readonly: field.readonly || false
      });
    }

    formGroup.appendChild(input);
    return formGroup;
  }
};

// Make Modal available globally
window.Modal = Modal;
