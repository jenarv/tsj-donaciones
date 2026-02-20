/**
 * FILTERS COMPONENT
 * Handles filter creation and management
 */

const Filters = {
  /**
   * Create filter bar
   */
  create(filters, onFilter) {
    const container = Utils.createElement('div', {
      className: 'filters'
    });

    filters.forEach(filter => {
      if (filter.type === 'select') {
        const select = this._createSelect(filter);
        select.addEventListener('change', onFilter);
        container.appendChild(select);
      } else if (filter.type === 'button') {
        const button = Utils.createElement('button', {
          className: filter.className || 'btn-refresh',
          onClick: filter.onClick
        }, [filter.label]);
        container.appendChild(button);
      }
    });

    return container;
  },

  /**
   * Create select filter
   */
  _createSelect(filter) {
    const select = Utils.createElement('select', {
      id: filter.id,
      name: filter.name || filter.id
    });

    // Add placeholder option
    if (filter.placeholder) {
      const option = Utils.createElement('option', {
        value: ''
      }, [filter.placeholder]);
      select.appendChild(option);
    }

    // Add options
    filter.options.forEach(opt => {
      const option = Utils.createElement('option', {
        value: opt.value
      }, [opt.label]);
      select.appendChild(option);
    });

    return select;
  },

  /**
   * Get filter values
   */
  getValues(filterIds) {
    const values = {};
    
    filterIds.forEach(id => {
      const element = document.getElementById(id);
      if (element) {
        values[id] = element.value;
      }
    });

    return values;
  },

  /**
   * Reset filters
   */
  reset(filterIds) {
    filterIds.forEach(id => {
      const element = document.getElementById(id);
      if (element && element.tagName === 'SELECT') {
        element.selectedIndex = 0;
      } else if (element && element.tagName === 'INPUT') {
        element.value = '';
      }
    });
  }
};

// Make Filters available globally
window.Filters = Filters;
