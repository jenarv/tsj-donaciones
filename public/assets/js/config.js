/**
 * CONFIGURATION
 * Global configuration and constants
 */

const CONFIG = {
  API_BASE_URL: 'http://localhost/tsj-donaciones/api',
  GOOGLE_CLIENT_ID: '510268014825-jk7kh1s8aenls024de3gfots09q75bpk.apps.googleusercontent.com',
  GOOGLE_DOMAIN: 'zapopan.tecmm.edu.mx',
  
  // Form steps configuration
  STEPS: [
    { id: 1, label: 'Carrera' },
    { id: 2, label: 'Tipo de donación' },
    { id: 3, label: 'Registro' },
    { id: 4, label: 'Confirmación' }
  ],
  
  // Carreras (majors) - Using department codes that match the database
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
    { id: 'laboratorios', label: 'Laboratorios y Talleres'},
    { id: 'medica', label: 'Médica'},
    { id: 'deportes', label: 'Deportes'}
  ],
  
  // Active status for requests
  ACTIVE_STATUS: ['Reservado', 'Aprobado', 'En_espera', 'Entregado']
};

// Global state
const STATE = {
  currentUser: null,
  canSubmitRequest: false,
  hasActiveRequest: false,
  currentStep: 1,
  selectedPackageId: null,
  departamento: null,
  currentCategory: 'laboratorios',
  articlesData: []
};