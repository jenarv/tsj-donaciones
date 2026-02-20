/**
 * CONFIGURATION
 * Global configuration and constants
 */

const CONFIG = {
  API_BASE_URL: 'http://localhost/tsj-donaciones/api',
  GOOGLE_CLIENT_ID: '510268014825-jk7kh1s8aenls024de3gfots09q75bpk.apps.googleusercontent.com', // Add your Google Client ID here
  
  // Tab configuration
  TABS: [
    { id: 'solicitudes', name: 'Solicitudes', module: 'Solicitudes' },
    { id: 'articulos', name: 'Artículos', module: 'Articulos' },
  ],
  
  // Status options
  STATUS_OPTIONS: [
    { value: 'Reservado', label: 'Reservado' },
    { value: 'Aprobado', label: 'Aprobado' },
    { value: 'En_espera', label: 'En espera' },
    { value: 'Entregado', label: 'Entregado' },
    { value: 'Rechazado', label: 'Rechazado' },
    { value: 'Expirado', label: 'Expirado' }
  ],
  CARRERAS: [
    { value: 'ICIV', label: 'Ing. Civil' },
    { value: 'IIND', label: 'Ing. Industrial' },
    { value: 'ISIC', label: 'Ing. en Sistemas Computacionales' },
    { value: 'IELEC', label: 'Ing. Electrónica' },
    { value: 'IGE', label: 'Ing. en Gestión Empresarial' },
    { value: 'GAST', label: 'Gastronomía' },
    { value: 'IELEM', label: 'Ing. Electromecánica' },
    { value: 'ARQ', label: 'Arquitectura' },
    { value: 'MELEC', label: 'Maestría en Electrónica' },
    { value: 'MSIC', label: 'Maestría en Sistemas Computacionales' }
  ],
  
  // Categories
  CATEGORIES: [
    { value: 'Laboratorio', label: 'Laboratorio' },
    { value: 'Medica', label: 'Médica' },
    { value: 'Deportes', label: 'Deportes' },
    { value: 'Otro', label: 'Otro' }
  ],
  
  // Image upload settings
  IMAGE: {
    MAX_SIZE: 5 * 1024 * 1024, // 5MB
    ALLOWED_TYPES: ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
  }
};

// Global state
const STATE = {
  currentUser: null,
  activeTab: 'solicitudes'
};
