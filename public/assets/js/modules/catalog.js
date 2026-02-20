/**
 * CATALOG MODULE - Student View
 * Shows only articles from student's department + universal categories
 */

const Catalog = {
  studentInfo: null,
  availableCategories: [],
  
  /**
   * Initialize catalog
   */
  async init(studentId) {
    this.studentId = studentId;
    await this.loadStudentInfo();
    await this.loadAvailableCategories();
  },

  /**
   * Load student information
   */
  async loadStudentInfo() {
    // This should be called from your auth system
    // For now, assuming it's available in STATE or passed as parameter
    try {
      // You might already have this in session/state
      console.log('Loading student info for ID:', this.studentId);
    } catch (error) {
      console.error('Error loading student info:', error);
    }
  },

  /**
   * Load categories available to student
   */
  async loadAvailableCategories() {
    try {
      const data = await Utils.apiRequest(
        `/categorias/disponibles.php?tipo=estudiante&id=${this.studentId}`
      );
      
      if (data.success) {
        this.availableCategories = data.data;
        console.log(`Student has access to ${this.availableCategories.length} categories`);
      }
    } catch (error) {
      console.error('Error loading categories:', error);
      this.availableCategories = [];
    }
  },

  /**
   * Load articles for a category
   */
  async load(categoria) {
    try {
      Loader.show();
      
      console.log('=== CATALOG LOAD DEBUG ===');
      console.log('STATE.departamento:', STATE.departamento);
      console.log('categoria:', categoria);
      
      // Build URL with carrera parameter for proper filtering
      let url = `/articulos/disponibles.php`;
      
      const params = [];
      
      // Send carrera from STATE.departamento (captured in Step 1)
      if (STATE.departamento) {
        params.push(`carrera=${STATE.departamento}`);
        console.log('✅ Adding carrera parameter:', STATE.departamento);
      } else {
        console.warn('⚠️ No departamento in STATE - items will be limited to Universal only');
      }
      
      if (categoria) {
        params.push(`categoria=${categoria}`);
      }
      
      if (params.length > 0) {
        url += '?' + params.join('&');
      }
      
      console.log('📡 API URL:', url);
      
      const data = await Utils.apiRequest(url);
      
      if (!data.success) throw new Error(data.error);
      
      STATE.articlesData = data.data;
      
      // Log debug info if available
      if (data.debug_info) {
        console.log('📊 API Response Debug Info:', data.debug_info);
        console.log('📦 Articles found:', data.count);
      }
      
      this.render(data.data);
      
    } catch (error) {
      console.error('❌ Error loading catalog:', error);
      this.showError();
    } finally {
      Loader.hide();
    }
  },

  /**
   * Render articles
   */
  render(articles) {
    const container = document.getElementById('product-list');
    
    if (!articles || articles.length === 0) {
      const deptName = STATE.departamento ? `carrera ${STATE.departamento}` : 'tu carrera';
      container.innerHTML = `
        <div class="no-results">
          <div class="no-results-icon">📦</div>
          <p>No hay artículos disponibles en esta categoría.</p>
          <p style="color: #666; font-size: 14px; margin-top: 10px;">
            Puedes ver artículos de: ${deptName} y categorías universales (Deportes, Médica)
          </p>
        </div>
      `;
      return;
    }
    
    container.innerHTML = articles.map(art => {
      // Add visual indicator for universal vs departmental
      const categoryBadge = art.tipo_acceso === 'Universal'
        ? '<span class="category-badge universal">Universal</span>'
        : '<span class="category-badge departmental">Tu Departamento</span>';
      
      return `
        <div class="product-card" data-id="${art.id_paquete}">
          <div class="product-image">
            ${art.imagen_url 
              ? `<img src="${art.imagen_url}" alt="${art.nombre}" onerror="this.parentElement.innerHTML='<div class=\\'placeholder-image\\'>📦</div>'">`
              : '<div class="placeholder-image">📦</div>'
            }
          </div>
          <div class="product-info">
            <h4>${art.nombre}</h4>
            <p class="product-category">${art.nombre_categoria} ${categoryBadge}</p>
            <p class="product-id">ID: ${art.id_paquete}</p>
            ${art.descripcion ? `<p class="product-description">${art.descripcion}</p>` : ''}
            <p class="product-price">$${parseFloat(art.precio_estimado).toFixed(2)}</p>
            ${art.enlace_referencia 
              ? `<a href="${art.enlace_referencia}" target="_blank" rel="noopener noreferrer" class="product-link">Ver referencia</a>` 
              : ''
            }
          </div>
          <button type="button" class="btn-select" onclick="Catalog.select('${art.id_paquete}', '${Utils.escapeHtml(art.nombre)}')">
            Seleccionar
          </button>
        </div>
      `;
    }).join('');
  },

  /**
   * Select an article
   */
  select(id, name) {
    // Remove previous selection
    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('selected'));
    
    // Highlight selected card
    const card = document.querySelector(`[data-id="${id}"]`);
    if (card) card.classList.add('selected');
    
    // Store selection
    STATE.selectedPackageId = id;
    document.getElementById('paquete').value = id;
  },

  /**
   * Show error message
   */
  showError() {
    document.getElementById('product-list').innerHTML = `
      <div class="error-message">
        <div class="error-icon">⚠️</div>
        <p>Error al cargar los artículos</p>
        <button onclick="Catalog.load(STATE.currentCategory)" class="btn-retry">
          Reintentar
        </button>
      </div>
    `;
  },

  /**
   * Get accessible category codes for student
   */
  getAccessibleCategories() {
    return this.availableCategories.map(cat => cat.codigo_categoria);
  }
};

// Make Catalog available globally
window.Catalog = Catalog;

// CSS for badges (add to your stylesheet or inject)
const style = document.createElement('style');
style.textContent = `
  .category-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 5px;
  }
  
  .category-badge.universal {
    background-color: #4CAF50;
    color: white;
  }
  
  .category-badge.departmental {
    background-color: #2196F3;
    color: white;
  }
  
  .product-category {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    color: #666;
    font-size: 14px;
  }
  
  .no-results {
    text-align: center;
    padding: 60px 20px;
  }
  
  .no-results-icon {
    font-size: 64px;
    margin-bottom: 20px;
  }
  
  .error-message {
    text-align: center;
    padding: 60px 20px;
  }
  
  .error-icon {
    font-size: 64px;
    margin-bottom: 20px;
  }
  
  .btn-retry {
    margin-top: 20px;
    padding: 10px 20px;
    background-color: #2196F3;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
  }
  
  .btn-retry:hover {
    background-color: #1976D2;
  }
`;
document.head.appendChild(style);