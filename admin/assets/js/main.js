/**
 * MAIN APPLICATION  
 * Main initialization and navigation
 * FIX: Improved tab switching with proper async handling
 */

const App = {
  /**
   * Initialize application
   */
  init() {
    // Initialize authentication
    Auth.init();
  },

  /**
   * Initialize dashboard after login
   */
  initDashboard() {
    this.setupNavigation();
    this.loadInitialTab();
  },

  /**
   * Setup navigation tabs
   */
  setupNavigation() {
    const navContainer = document.getElementById('nav-tabs');
    navContainer.innerHTML = '';

    CONFIG.TABS.forEach((tab, index) => {
      const button = Utils.createElement('button', {
        className: `nav-tab ${index === 0 ? 'active' : ''}`,
        onClick: () => this.switchTab(tab.id)
      }, [tab.name]);

      navContainer.appendChild(button);
    });
  },

  /**
   * Load initial tab
   */
  async loadInitialTab() {
    const firstTab = CONFIG.TABS[0];
    if (firstTab) {
      await this.loadTab(firstTab.id);
    }
  },

  /**
   * Switch tab
   * FIX: Properly handle both new and existing tabs
   */
  async switchTab(tabId) {
    // Update nav tabs UI
    document.querySelectorAll('.nav-tab').forEach(tab => {
      tab.classList.remove('active');
    });
    
    // Find and activate the clicked button
    const tabConfig = CONFIG.TABS.find(t => t.id === tabId);
    if (tabConfig) {
      const clickedButton = Array.from(document.querySelectorAll('.nav-tab')).find(
        btn => btn.textContent === tabConfig.name
      );
      if (clickedButton) {
        clickedButton.classList.add('active');
      }
    }

    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
      content.classList.remove('active');
    });

    // Check if tab is already rendered
    const existingTab = document.getElementById(`tab-${tabId}`);
    
    if (existingTab) {
      // Tab exists - show it and reload data
      existingTab.classList.add('active');
      
      // Reload the module's data
      const module = window[tabConfig.module];
      if (module && typeof module.load === 'function') {
        await module.load();
      }
    } else {
      // Tab doesn't exist - initialize it
      await this.loadTab(tabId);
    }

    STATE.activeTab = tabId;
  },

  /**
   * Load tab module (initialize for first time)
   */
  async loadTab(tabId) {
    const tab = CONFIG.TABS.find(t => t.id === tabId);
    if (!tab) return;

    const module = window[tab.module];
    if (module && typeof module.init === 'function') {
      await module.init();
      
      // Make sure the tab is visible after init
      const tabContent = document.getElementById(`tab-${tabId}`);
      if (tabContent) {
        tabContent.classList.add('active');
      }
    }
  }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  App.init();
});

// Make App available globally
window.App = App;
