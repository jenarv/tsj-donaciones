/**
 * TABLE COMPONENT
 * Handles table creation and rendering
 */

const Table = {
  /**
   * Create table
   */
  create(columns, data, options = {}) {
    const table = Utils.createElement('table', {
      className: 'data-table'
    });

    // Create thead
    const thead = this._createHeader(columns);
    table.appendChild(thead);

    // Create tbody
    const tbody = this._createBody(columns, data, options);
    table.appendChild(tbody);

    return table;
  },

  /**
   * Create table header
   */
  _createHeader(columns) {
    const thead = Utils.createElement('thead');
    const tr = Utils.createElement('tr');

    columns.forEach(col => {
      const th = Utils.createElement('th', {}, [col.label]);
      tr.appendChild(th);
    });

    thead.appendChild(tr);
    return thead;
  },

  /**
   * Create table body
   */
  _createBody(columns, data, options) {
    const tbody = Utils.createElement('tbody');

    if (!data || data.length === 0) {
      const tr = Utils.createElement('tr');
      const td = Utils.createElement('td', {
        colspan: columns.length,
        style: 'text-align: center; padding: 40px;'
      }, ['No hay datos para mostrar']);
      tr.appendChild(td);
      tbody.appendChild(tr);
      return tbody;
    }

    data.forEach(row => {
      const tr = Utils.createElement('tr', {
        className: options.clickable ? 'clickable-row' : '',
        onClick: options.onRowClick ? () => options.onRowClick(row) : null
      });

      columns.forEach(col => {
        const td = Utils.createElement('td');
        
        if (col.render) {
          // Custom render function
          const content = col.render(row[col.key], row);
          if (typeof content === 'string') {
            td.innerHTML = content;
          } else if (content instanceof Node) {
            td.appendChild(content);
          }
        } else {
          // Default render
          td.textContent = row[col.key] || '';
        }
        
        tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });

    return tbody;
  },

  /**
   * Render table in container
   */
  render(containerId, columns, data, options = {}) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const tableContainer = Utils.createElement('div', {
      className: 'table-container'
    });

    const table = this.create(columns, data, options);
    tableContainer.appendChild(table);

    container.innerHTML = '';
    container.appendChild(tableContainer);
  },

  /**
   * Common renderers
   */
  renderers: {
    /**
     * Render status badge
     */
    statusBadge(status) {
      return `<span class="status-badge status-${status}">${status}</span>`;
    },

    /**
     * Render image thumbnail
     */
    imageThumbnail(imageUrl, alt = 'Image') {
      if (!imageUrl) {
        return '<div style="width:60px;height:60px;background:#f0f0f0;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:24px;">📦</div>';
      }
      return `<img src="${imageUrl}" alt="${alt}" class="article-image-thumbnail" onerror="handleImageError(this)">`;
    },

    /**
     * Render date src="/tsj-donaciones/${imageUrl}"
     */
    date(dateString) {
      return Utils.formatDate(dateString);
    },

    /**
     * Render currency
     */
    currency(amount) {
      return `$${parseFloat(amount).toFixed(2)}`;
    },

    /**
     * Render action buttons
     */
    actionButtons(buttons) {
      const container = Utils.createElement('div', {
        className: 'action-buttons'
      });

      buttons.forEach(btn => {
        const button = Utils.createElement('button', {
          className: `btn-action btn-${btn.type}`,
          onClick: btn.onClick
        }, [btn.label]);
        container.appendChild(button);
      });

      return container;
    }
  }
};

// Make Table available globally
window.Table = Table;
