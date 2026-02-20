/**
 * AUTHENTICATION MODULE
 * Handles login, logout, and session management
 */

const Auth = {
  /**
   * Initialize authentication
   */
  init() {
    this.setupLoginForm();
    this.setupGoogleSignIn();
    this.checkSession();
  },

  /**
   * Setup login form
   */
  setupLoginForm() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;
    
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.login();
    });
  },

  /**
   * Login with email and password
   */
  async login() {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    try {
      const data = await Utils.apiRequest('/auth/login.php', {
        method: 'POST',
        body: JSON.stringify({ email, password })
      });
      
      if (!data.success) {
        Utils.notify(data.error || 'Error al iniciar sesión', 'error');
        return;
      }
      
      STATE.currentUser = data.usuario;
      this.showDashboard();
      
    } catch (error) {
      console.error('Error:', error);
      Utils.notify('Error al conectar con el servidor', 'error');
    }
  },

  /**
   * Setup Google Sign-In
   */
  setupGoogleSignIn() {
    const googleBtn = document.getElementById('admin-google-signin');
    if (!googleBtn || !CONFIG.GOOGLE_CLIENT_ID) return;
    
    googleBtn.addEventListener('click', () => {
      const redirectUri = encodeURIComponent(
        window.location.origin + '/tsj-donaciones/api/auth/google-callback-admin.php'
      );
      const scope = encodeURIComponent('email profile');
      
      const authUrl = 
        `https://accounts.google.com/o/oauth2/v2/auth?` +
        `client_id=${CONFIG.GOOGLE_CLIENT_ID}&` +
        `redirect_uri=${redirectUri}&` +
        `response_type=code&` +
        `scope=${scope}&` +
        `access_type=online&` +
        `prompt=select_account`;
      
      window.location.href = authUrl;
    });
  },

  /**
   * Check if session exists
   */
  async checkSession() {
    try {
      const data = await Utils.apiRequest('/auth/check-admin-auth.php');
      
      if (data.success && data.usuario) {
        STATE.currentUser = data.usuario;
        this.showDashboard();
      }
    } catch (error) {
      console.log('No active session');
    }
  },

  /**
   * Show dashboard
   */
  showDashboard() {
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('admin-dashboard').style.display = 'block';
    
    // Update user info
    document.getElementById('user-name').textContent = STATE.currentUser.nombre;
    
    // Mejorar el badge de rol para mostrar departamento si aplica
    const rolElement = document.getElementById('user-role');
    if (STATE.currentUser.rol_tipo === 'Super_Admin') {
      rolElement.textContent = 'Super Admin';
    } else {
      // Admin Departamental - mostrar departamento
      const departamento = STATE.currentUser.nombre_departamento || STATE.currentUser.departamento || 'Sin Departamento';
      rolElement.textContent = `Admin ${departamento}`;
    }
    
    // Initialize dashboard
    App.initDashboard();
  },

  /**
   * Logout
   */
  async logout() {
    try {
      await Utils.apiRequest('/auth/logout.php', {
        method: 'POST'
      });
    } catch (error) {
      console.error('Error:', error);
    }
    
    location.reload();
  }
};

// Make logout available globally for inline handlers
window.Auth = Auth;
