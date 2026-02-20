/**
 * UTILITY FUNCTIONS
 * Helper functions used throughout the application
 */

const Utils = {
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
   * Create element with attributes
   */
  createElement(tag, attributes = {}, children = []) {
    const element = document.createElement(tag);
    
    Object.entries(attributes).forEach(([key, value]) => {
      if (key === 'className') {
        element.className = value;
      } else if (key === 'dataset') {
        Object.entries(value).forEach(([dataKey, dataValue]) => {
          element.dataset[dataKey] = dataValue;
        });
      } else if (key.startsWith('on') && typeof value === 'function') {
        element.addEventListener(key.substring(2).toLowerCase(), value);
      } else {
        element.setAttribute(key, value);
      }
    });
    
    children.forEach(child => {
      if (typeof child === 'string') {
        element.appendChild(document.createTextNode(child));
      } else if (child instanceof Node) {
        element.appendChild(child);
      }
    });
    
    return element;
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
   * Get category name
   */
  getCategoryName(categoria) {
    const cat = CONFIG.CATEGORIES.find(c => c.id === categoria);
    return cat ? cat.label : categoria;
  },

  /**
   * Check for auth errors in URL
   */
  checkAuthErrors() {
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    
    if (error) {
      const errorMessages = {
        'auth_failed': 'Error de autenticación. Por favor intenta de nuevo.',
        'domain_not_allowed': 'Debes usar tu correo institucional (@zapopan.tecmm.edu.mx)'
      };
      
      const message = errorMessages[error] || decodeURIComponent(error);
      Notification.show(message, 'error');
      
      // Clean URL
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  }
};
