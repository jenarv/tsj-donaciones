/**
 * AUTHENTICATION MODULE
 * Handles Google authentication and user session
 */

const Auth = {
  /**
   * Initialize authentication
   */
  async init() {
    Utils.checkAuthErrors();
    await this.checkSession();
    this.setupEventListeners();
  },

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    const signInBtn = document.getElementById('google-signin-btn');
    if (signInBtn) {
      signInBtn.addEventListener('click', () => this.handleGoogleSignIn());
    }
    
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => this.handleLogout());
    }
  },

  /**
   * Check session
   */
  async checkSession() {
    try {
      const data = await Utils.apiRequest('/auth/check-student-auth.php');
      
      if (data.authenticated) {
        STATE.currentUser = data.student;
        this.handleAuthenticatedState(data);
      } else {
        this.handleUnauthenticatedState();
      }
    } catch (error) {
      console.error('Error checking auth:', error);
      this.handleUnauthenticatedState();
    }
  },

  /**
   * Handle authenticated state
   */
  handleAuthenticatedState(data) {
    // Hide login section
    document.getElementById('login-required').style.display = 'none';
    
    // Show user info
    const userInfoEl = document.getElementById('user-info');
    userInfoEl.style.display = 'block';
    
    // Populate user details
    document.getElementById('user-picture').src = data.student.picture || '/tsj-donaciones/img/default-avatar.png';
    document.getElementById('user-name').textContent = data.student.nombre;
    document.getElementById('user-email').textContent = data.student.email;
    
    // Auto-fill email field
    const emailField = document.getElementById('email');
    if (emailField) {
      emailField.value = data.student.email;
      emailField.readOnly = true;
    }
    
    // Handle request status
    if (data.solicitud && data.solicitud.existe) {
      STATE.canSubmitRequest = data.solicitud.puede_solicitar;
      STATE.hasActiveRequest = CONFIG.ACTIVE_STATUS.includes(data.solicitud.estatus);
      
      if (STATE.hasActiveRequest) {
        this.showRequestStatus(data.solicitud);
        FormSteps.disableForm();
      } else {
        this.hideRequestStatus();
        FormSteps.enableForm();
      }
    } else {
      STATE.canSubmitRequest = true;
      STATE.hasActiveRequest = false;
      this.hideRequestStatus();
      FormSteps.enableForm();
    }
  },

  /**
   * Handle unauthenticated state
   */
  handleUnauthenticatedState() {
    document.getElementById('login-required').style.display = 'block';
    document.getElementById('user-info').style.display = 'none';
    FormSteps.disableForm();
  },

  /**
   * Show request status
   */
  showRequestStatus(solicitud) {
    const statusEl = document.getElementById('request-status');
    statusEl.style.display = 'block';
    
    let statusHTML = '';
    let statusClass = '';
    
    switch(solicitud.estatus) {
      case 'Reservado':
        statusClass = 'status-pending';
        statusHTML = `
          <div class="status-icon">⏳</div>
          <h4>Solicitud Pendiente</h4>
          <p>Tu solicitud para <strong>${solicitud.nombre_articulo}</strong> está siendo revisada.</p>
          <p class="status-note">No puedes enviar otra solicitud hasta que ésta sea procesada.</p>
        `;
        break;
      case 'Aprobado':
        statusClass = 'status-approved';
        statusHTML = `
          <div class="status-icon">✓</div>
          <h4>Solicitud Aprobada</h4>
          <p>Tu solicitud para <strong>${solicitud.nombre_articulo}</strong> ha sido aprobada.</p>
          <p class="status-note">Revisa tu correo para los siguientes pasos.</p>
        `;
        break;
      case 'En_espera':
        statusClass = 'status-waiting';
        statusHTML = `
          <div class="status-icon">⏸</div>
          <h4>En Espera</h4>
          <p>Tu solicitud para <strong>${solicitud.nombre_articulo}</strong> está en espera.</p>
          <p class="status-note">No puedes enviar otra solicitud hasta que ésta sea procesada.</p>
        `;
        break;
      case 'Entregado':
        statusClass = 'status-approved';
        statusHTML = `
          <div class="status-icon">✓</div>
          <h4>Donación Entregada</h4>
          <p>Tu donación para <strong>${solicitud.nombre_articulo}</strong> ha sido entregada y completada.</p>
          <p class="status-note">No puedes llenar el formulario de nuevo.</p>
        `;
        break;
    }
    
    statusEl.className = `request-status ${statusClass}`;
    statusEl.innerHTML = statusHTML;
  },

  /**
   * Hide request status
   */
  hideRequestStatus() {
    const statusEl = document.getElementById('request-status');
    if (statusEl) {
      statusEl.style.display = 'none';
    }
  },

  /**
   * Handle Google Sign-In
   */
  handleGoogleSignIn() {
    const redirectUri = encodeURIComponent(
      window.location.origin + '/tsj-donaciones/api/auth/google-callback-student.php'
    );
    const scope = encodeURIComponent('email profile');
    
    const authUrl = 
      `https://accounts.google.com/o/oauth2/v2/auth?` +
      `client_id=${CONFIG.GOOGLE_CLIENT_ID}&` +
      `redirect_uri=${redirectUri}&` +
      `response_type=code&` +
      `scope=${scope}&` +
      `hd=${CONFIG.GOOGLE_DOMAIN}&` +
      `access_type=online&` +
      `prompt=select_account`;
    
    window.location.href = authUrl;
  },

  /**
   * Handle logout
   */
  async handleLogout() {
    try {
      await Utils.apiRequest('/auth/logout.php', { method: 'POST' });
      window.location.reload();
    } catch (error) {
      console.error('Error:', error);
      window.location.reload();
    }
  }
};

// Export for debugging
window.TSJAuth = {
  getCurrentUser: () => STATE.currentUser,
  canSubmit: () => STATE.canSubmitRequest,
  hasActive: () => STATE.hasActiveRequest,
  refresh: () => Auth.checkSession()
};

window.Auth = Auth;
