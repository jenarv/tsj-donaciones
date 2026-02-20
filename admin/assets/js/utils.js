/**
 * UTILITY FUNCTIONS
 * Helper functions used throughout the application
 * FIX: Fixed createElement to handle boolean properties like readonly correctly
 */

const Utils = {
  /**
   * Format date to Spanish locale
   */
  formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('es-MX', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  },

  /**
   * Check if user has required permission
   */
  hasPermission(requiredRole) {
    if (!STATE.currentUser) return false;
    const userLevel = CONFIG.ROLE_HIERARCHY[STATE.currentUser.rol] || 0;
    const requiredLevel = CONFIG.ROLE_HIERARCHY[requiredRole] || 0;
    return userLevel >= requiredLevel;
  },

  /**
   * Make API request
   */
  async apiRequest(endpoint, options = {}) {
    const defaultOptions = {
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      }
    };

    // If body is FormData, remove Content-Type header
    if (options.body instanceof FormData) {
      delete defaultOptions.headers['Content-Type'];
    }

    const response = await fetch(`${CONFIG.API_BASE_URL}${endpoint}`, {
      ...defaultOptions,
      ...options
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  },

  /**
   * Show notification (simple alert for now, can be improved)
   */
  notify(message, type = 'info') {
    // TODO: Implement better notification system
    alert(message);
  },

  /**
   * Debounce function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  /**
   * Validate image file
   */
  validateImageFile(file) {
    if (!file) return { valid: false, error: 'No se seleccionó archivo' };
    
    if (!CONFIG.IMAGE.ALLOWED_TYPES.includes(file.type)) {
      return { 
        valid: false, 
        error: 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WEBP' 
      };
    }
    
    if (file.size > CONFIG.IMAGE.MAX_SIZE) {
      return { 
        valid: false, 
        error: 'La imagen es demasiado grande. Tamaño máximo: 5MB' 
      };
    }
    
    return { valid: true };
  },

  /**
   * Create image preview from file
   */
  createImagePreview(file, callback) {
    const reader = new FileReader();
    reader.onload = (e) => callback(e.target.result);
    reader.readAsDataURL(file);
  },

  /**
   * Handle image loading errors
   */
  handleImageError(img) {
    if (img.getAttribute('data-error-handled')) return;
    
    img.setAttribute('data-error-handled', 'true');
    
    const placeholder = document.createElement('div');
    placeholder.style.cssText = 'width:60px;height:60px;background:#f0f0f0;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:24px;';
    placeholder.textContent = '📦';
    placeholder.title = 'Imagen no disponible';
    
    if (img.parentElement) {
      img.parentElement.replaceChild(placeholder, img);
    }
  },

  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  /**
   * Create element with attributes
   * FIX: Added proper handling for boolean properties like readonly, disabled, checked
   */
  createElement(tag, attributes = {}, children = []) {
    const element = document.createElement(tag);
    
    // FIX: Map of HTML attributes to their corresponding DOM property names
    // Some properties use camelCase in JavaScript but lowercase in HTML
    const booleanPropMap = {
      'readonly': 'readOnly',
      'disabled': 'disabled',
      'checked': 'checked',
      'selected': 'selected',
      'required': 'required'
    };
    
    Object.entries(attributes).forEach(([key, value]) => {
      if (key === 'className') {
        element.className = value;
      } else if (key === 'dataset') {
        Object.entries(value).forEach(([dataKey, dataValue]) => {
          element.dataset[dataKey] = dataValue;
        });
      } else if (key.startsWith('on') && typeof value === 'function') {
        element.addEventListener(key.substring(2).toLowerCase(), value);
      } else if (booleanPropMap[key]) {
        // FIX: Set boolean properties using correct DOM property name
        // Only set if value is truthy to avoid setting false values
        if (value) {
          element[booleanPropMap[key]] = true;
        } else {
          element[booleanPropMap[key]] = false;
        }
      } else if (value !== undefined && value !== null) {
        element.setAttribute(key, value);
      }
    });
    
    children.forEach(child => {
      if (child === null || child === undefined) {
        // Skip null/undefined children
        return;
      }
      if (typeof child === 'string') {
        element.appendChild(document.createTextNode(child));
      } else if (child instanceof Node) {
        element.appendChild(child);
      }
    });
    
    return element;
  },

  /**
   * Show loading state
   */
  showLoading(container, message = 'Cargando...') {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">⏳</div>
        <p>${message}</p>
      </div>
    `;
  },

  /**
   * Show empty state
   */
  showEmptyState(container, message = 'No hay datos para mostrar', icon = '📭') {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">${icon}</div>
        <h3>Sin resultados</h3>
        <p>${message}</p>
      </div>
    `;
  }
};

// Make handleImageError available globally for inline handlers
window.handleImageError = Utils.handleImageError;
