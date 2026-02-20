/**
 * SOLICITUDES MODULE
 * Handles solicitudes (requests) management
 */

const Solicitudes = {
  data: [],

  /**
   * Initialize solicitudes tab
   */
  async init() {
    this.renderTab();
    await this.load();
  },

  /**
   * Render tab content
   */
  renderTab() {
    const container = document.getElementById('tab-container');
    
    const content = Utils.createElement('div', {
      id: 'tab-solicitudes',
      className: 'tab-content active'
    });

    // Header
    const header = Utils.createElement('div', {
      className: 'content-header'
    }, [
      Utils.createElement('h2', {}, ['Gestión de Solicitudes']),
      this._createFilters()
    ]);
    content.appendChild(header);

    // List container
    const listContainer = Utils.createElement('div', {
      id: 'solicitudes-list',
      className: 'table-container'
    });
    content.appendChild(listContainer);

    container.appendChild(content);

    // Create detail modal
    this._createDetailModal();
  },

  /**
   * Create filters
   */
  _createFilters() {
    const filters = [
      {
        type: 'select',
        id: 'filter-estatus',
        placeholder: 'Todos los estatus',
        options: CONFIG.STATUS_OPTIONS
      }
    ];

    // Solo mostrar filtro de carrera para Super Admins
    if (STATE.currentUser && STATE.currentUser.rol_tipo === 'Super_Admin') {
      filters.push({
        type: 'select',
        id: 'filter-carrera',
        placeholder: 'Todas las carreras',
        options: CONFIG.CARRERAS
      });
    }

    filters.push({
      type: 'button',
      label: 'Actualizar',
      className: 'btn-refresh',
      onClick: () => this.load()
    });

    return Filters.create(filters, () => this.load());
  },

  /**
   * Load solicitudes
   */
  async load() {
    const listContainer = document.getElementById('solicitudes-list');
    Utils.showLoading(listContainer, 'Cargando solicitudes...');

    try {
      // Solo obtener filtros que existan en el DOM
      const filterIds = ['filter-estatus'];
      if (document.getElementById('filter-carrera')) {
        filterIds.push('filter-carrera');
      }
      
      const filters = Filters.getValues(filterIds);
      
      let url = '/admin/solicitudes.php?';
      if (filters['filter-estatus']) url += `estatus=${filters['filter-estatus']}&`;
      if (filters['filter-carrera']) url += `carrera=${filters['filter-carrera']}`;

      const data = await Utils.apiRequest(url);

      if (!data.success) {
        throw new Error(data.error);
      }

      this.data = data.data;
      this.render();

    } catch (error) {
      console.error('Error:', error);
      Utils.notify('Error al cargar solicitudes', 'error');
      Utils.showEmptyState(listContainer, 'Error al cargar solicitudes');
    }
  },

  /**
   * Render solicitudes table
   */
  render() {
    const columns = [
      { key: 'id_solicitud', label: 'ID' },
      { 
        key: 'imagen_url', 
        label: 'Imagen',
        render: (value, row) => Table.renderers.imageThumbnail(value, row.nombre_articulo)
      },
      { 
        key: 'nombre_articulo', 
        label: 'Artículo',
        render: (value, row) => `${value}<br><small>${row.id_paquete}</small>`
      },
      { key: 'carrera', label: 'Carrera' },
      { 
        key: 'num_donadores', 
        label: 'Donadores',
        render: (value) => `${value} persona(s)`
      },
      { 
        key: 'estatus', 
        label: 'Estatus',
        render: (value) => Table.renderers.statusBadge(value)
      },
      { 
        key: 'fecha_solicitud', 
        label: 'Fecha',
        render: (value) => Table.renderers.date(value)
      },
      { 
        key: 'actions', 
        label: 'Acciones',
        render: (value, row) => this._renderActions(row)
      }
    ];

    Table.render('solicitudes-list', columns, this.data);
  },

  /**
   * Render action buttons
   */
  _renderActions(solicitud) {
    const buttons = [];

    // View details
    buttons.push({
      type: 'view',
      label: 'Ver',
      onClick: () => this.viewDetail(solicitud.id_solicitud)
    });

    // Approve/Reject - All admins can manage their department's solicitudes
    if (solicitud.estatus === 'Reservado') {
      buttons.push({
        type: 'approve',
        label: 'Aprobar',
        onClick: () => this.manage(solicitud.id_solicitud, 'aprobar')
      });
      buttons.push({
        type: 'reject',
        label: 'Rechazar',
        onClick: () => this.manage(solicitud.id_solicitud, 'rechazar')
      });
    }

    // Mark as delivered - All admins can deliver
    if (solicitud.estatus === 'Aprobado') {
      buttons.push({
        type: 'deliver',
        label: 'Entregar',
        onClick: () => this.manage(solicitud.id_solicitud, 'entregar')
      });
    }

    return Table.renderers.actionButtons(buttons);
  },

  /**
   * View solicitud detail
   */
  async viewDetail(id) {
    try {
      const data = await Utils.apiRequest(`/admin/solicitud-detalle.php?id=${id}`);

      if (!data.success) {
        throw new Error(data.error);
      }

      this._showDetailModal(data.data);

    } catch (error) {
      console.error('Error:', error);
      Utils.notify('Error al cargar detalles', 'error');
    }
  },

  /**
   * Create detail modal
   */
  _createDetailModal() {
    const modalContent = Utils.createElement('div', {
      id: 'modal-solicitud-content',
      className: 'solicitud-details'
    });

    Modal.create('modal-solicitud', 'Detalles de la Solicitud', modalContent);
  },

  /**
   * Show detail modal
   */
  _showDetailModal(sol) {
    const content = document.getElementById('modal-solicitud-content');
    
    content.innerHTML = `
      <div class="solicitud-details">
        <h3>Información del Artículo</h3>
        
        ${sol.imagen_url ? `
          <div style="text-align: center; margin: 20px 0;">
            <img src="${sol.imagen_url}" 
                 alt="${sol.nombre_articulo}" 
                 class="article-image"
                 onerror="handleImageError(this)">
          </div>
        ` : `
          <div class="image-placeholder">
            <div class="image-placeholder-icon">📦</div>
            <p class="image-placeholder-text">Sin imagen disponible</p>
          </div>
        `}
        
        <p><strong>ID:</strong> ${sol.id_paquete}</p>
        <p><strong>Nombre:</strong> ${sol.nombre_articulo}</p>
        <p><strong>Descripción:</strong> ${sol.descripcion || 'N/A'}</p>
        
        <h3>Información de la Solicitud</h3>
        <p><strong>Estatus:</strong> <span class="status-badge status-${sol.estatus}">${sol.estatus}</span></p>
        <p><strong>Carrera:</strong> ${sol.carrera}</p>
        <p><strong>Fecha de solicitud:</strong> ${Utils.formatDate(sol.fecha_solicitud)}</p>
        ${sol.fecha_expiracion ? `<p><strong>Expira:</strong> ${Utils.formatDate(sol.fecha_expiracion)}</p>` : ''}
        
        <h3>Donadores</h3>
        ${this._renderDonadores(sol.donadores)}
        
        ${sol.notas_admin ? `
          <h3>Notas del Administrador</h3>
          <p>${sol.notas_admin}</p>
        ` : ''}
      </div>
    `;

    Modal.open('modal-solicitud');
  },

  /**
   * Render donadores list
   */
  _renderDonadores(donadores) {
    if (!donadores || donadores.length === 0) {
      return '<p>Sin información de donadores</p>';
    }

    const donadoresList = donadores.map(donador => {
      const badge = donador.es_representante === 1 ? ' <span class="badge-representante">Representante</span>' : '';
      return `
        <li>
          <strong>${badge}${donador.nombre_completo} (${donador.numero_control})</strong><br>
          ${donador.correo_institucional}
        </li>
      `;
    }).join('');

    return `<ul class="donadores-list">${donadoresList}</ul>`;
  },

  /**
   * Manage solicitud (approve, reject, deliver)
   */
  async manage(idSolicitud, accion) {
    const confirmMessages = {
      'aprobar': '¿Aprobar esta solicitud?',
      'rechazar': '¿Rechazar esta solicitud?',
      'entregar': '¿Marcar como entregado?'
    };

    if (!confirm(confirmMessages[accion])) {
      return;
    }

    let notas = null;
    if (accion === 'rechazar') {
      notas = prompt('Motivo del rechazo (opcional):');
    }

    try {
      const data = await Utils.apiRequest('/admin/gestionar-solicitud.php', {
        method: 'POST',
        body: JSON.stringify({
          id_solicitud: idSolicitud,
          accion: accion,
          notas: notas
        })
      });

      if (!data.success) {
        throw new Error(data.error);
      }

      Utils.notify('Solicitud actualizada correctamente', 'success');
      await this.load();

    } catch (error) {
      console.error('Error:', error);
      Utils.notify('Error al gestionar solicitud: ' + error.message, 'error');
    }
  }
};

// Make Solicitudes available globally
window.Solicitudes = Solicitudes;