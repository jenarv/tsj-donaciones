const FormSteps = {
  init() {
    this.renderSteps();
    this.setupInterceptor();
    this.setupCarreraListener();
  },
  
  setupCarreraListener() {
    // Add listener after a short delay to ensure DOM is ready
    setTimeout(() => {
      const carreraSelect = document.getElementById('carrera');
      if (carreraSelect) {
        carreraSelect.addEventListener('change', (e) => {
          STATE.departamento = e.target.value;
          console.log('🎯 Carrera changed:', STATE.departamento);
        });
        console.log('✅ Carrera listener attached');
      } else {
        console.error('❌ Carrera select not found for listener');
      }
    }, 100);
  },
  renderSteps() {
    const container = document.getElementById('form-steps-container');
    container.innerHTML = `
      ${this.getStep1HTML()}
      ${this.getStep2HTML()}
      ${this.getStep3HTML()}
      ${this.getStep4HTML()}
    `;
  },
  getStep1HTML() {
    return `<div class="form-step active" data-step="1"><div class="form-content"><h3 class="step-title">Información inicial</h3><p class="info-text"><strong>La cantidad a donar es de 1,000 pesos por persona.</strong> Lee las instrucciones y selecciona el ID del paquete. <strong>No adquieras el bien hasta que sea aprobado.</strong></p><div class="field-group"><label for="email">Correo <span class="required">*</span></label><input type="email" id="email" name="email" required></div><div class="field-group"><label for="carrera">Carrera <span class="required">*</span></label><select id="carrera" name="carrera" required><option value="">-- Selecciona tu carrera --</option>${CONFIG.CARRERAS.map(c => `<option value="${c.value}">${c.label}</option>`).join('')}</select></div><div class="button-group"><button type="button" class="btn-primary" onclick="FormSteps.next()">Siguiente</button></div></div></div>`;
  },
  getStep2HTML() {
    return `<div class="form-step" data-step="2"><div class="form-content"><h3>Tipo de donación</h3><div class="field-group"><label>Categoría:</label><div class="category-tabs">${CONFIG.CATEGORIES.map((c,i) => `<button type="button" class="category-tab ${i===0?'active':''}" onclick="FormSteps.switchCategory('${c.id}')">${c.label}</button>`).join('')}</div></div><div id="catalog-section"><div id="product-list" class="catalog-container"></div></div><div class="button-group"><button type="button" class="btn-secondary" onclick="FormSteps.prev()">Atrás</button><button type="button" class="btn-primary" onclick="FormSteps.next()">Siguiente</button></div></div></div>`;
  },
  getStep3HTML() {
    return `<div class="form-step" data-step="3"><div class="form-content"><h3>Información de registro</h3><div class="field-group"><label for="paquete">ID del paquete <span class="required">*</span></label><input type="text" id="paquete" readonly style="background:#f0f0f5;font-weight:bold;color:#5b6ad0"></div><div class="field-group"><label>Nombre del alumno <span class="required">*</span></label><p class="helper-text">Mayúsculas, apellidos primero. Si son varios, separa con comas.</p><input type="text" id="nombreAlumno" required></div><div class="field-group"><label>Número de control <span class="required">*</span></label><input type="text" id="numeroControl" required></div><div class="field-group"><label>Correo institucional <span class="required">*</span></label><input type="text" id="correoInstitucional" required></div><div class="button-group"><button type="button" class="btn-secondary" onclick="FormSteps.prev()">Atrás</button><button type="submit" class="btn-primary">Enviar</button><button type="button" class="btn-delete" onclick="FormSteps.reset()">Borrar</button></div></div></div>`;
  },
  getStep4HTML() {
    return `<div class="form-step" data-step="4"><div class="form-content confirmation-container"><div class="success-icon"><svg viewBox="0 0 50 50"><polyline points="10,25 20,35 40,15" /></svg></div><h3 class="confirmation-title">¡Formulario enviado!</h3><p class="confirmation-text">Tu propuesta ha sido recibida. Recibirás confirmación por correo.</p><p class="confirmation-text"><strong>Recuerda:</strong> No adquieras el bien hasta que sea aprobado.</p><div class="contact-box"><h4>¿Necesitas ayuda?</h4><p>Contacta al administrador</p></div><button type="button" class="btn-home" onclick="FormSteps.reset();location.reload()">Nueva solicitud</button></div></div>`;
  },
  next() {
    console.log('=== NEXT BUTTON CLICKED ===');
    console.log('Current step:', STATE.currentStep);
    
    if (!this.validate(STATE.currentStep)) return;
    
    // Capture carrera (departamento) from step 1
    if (STATE.currentStep === 1) {
      const carreraSelect = document.getElementById('carrera');
      console.log('Carrera select element:', carreraSelect);
      console.log('Carrera select value:', carreraSelect ? carreraSelect.value : 'NOT FOUND');
      
      if (carreraSelect && carreraSelect.value) {
        STATE.departamento = carreraSelect.value;
        console.log('✅ Carrera captured in STATE.departamento:', STATE.departamento);
      } else {
        console.error('❌ Failed to capture carrera!');
      }
    }
    
    if (STATE.currentStep === 2 && !STATE.selectedPackageId) {
      Notification.show('Selecciona un artículo', 'error');
      return;
    }
    
    document.querySelector(`.form-step[data-step="${STATE.currentStep}"]`).classList.remove('active');
    STATE.currentStep++;
    document.querySelector(`.form-step[data-step="${STATE.currentStep}"]`).classList.add('active');
    
    // Reload catalog when entering step 2 with the selected carrera
    if (STATE.currentStep === 2) {
      console.log('📍 Entering Step 2');
      console.log('STATE.departamento before load:', STATE.departamento);
      console.log('STATE.currentCategory:', STATE.currentCategory);
      Catalog.load(STATE.currentCategory);
    }
    
    Progress.update(STATE.currentStep);
    window.scrollTo({top:0,behavior:'smooth'});
  },
  prev() {
    document.querySelector(`.form-step[data-step="${STATE.currentStep}"]`).classList.remove('active');
    STATE.currentStep--;
    document.querySelector(`.form-step[data-step="${STATE.currentStep}"]`).classList.add('active');
    Progress.update(STATE.currentStep);
    window.scrollTo({top:0,behavior:'smooth'});
  },
  validate(step) {
    const stepEl = document.querySelector(`.form-step[data-step="${step}"]`);
    const inputs = stepEl.querySelectorAll('input[required], select[required]');
    let valid = true;
    inputs.forEach(inp => {
      if (!inp.value.trim()) { inp.classList.add('error'); valid = false; }
      else { inp.classList.remove('error'); }
    });
    if (!valid) Notification.show('Completa todos los campos', 'error');
    return valid;
  },
  switchCategory(cat) {
    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    STATE.currentCategory = cat;
    
    // Only load if we have a carrera selected
    if (STATE.departamento) {
      Catalog.load(cat);
    } else {
      console.warn('No carrera selected yet');
    }
  },
  reset() {
    document.getElementById('donationForm').reset();
    STATE.currentStep = 1;
    STATE.selectedPackageId = null;
    document.querySelectorAll('.form-step').forEach((s,i) => s.classList.toggle('active', i===0));
    Progress.update(1);
  },
  disableForm() {
    // 1. Obtener los elementos que queremos desvanecer
    const formContainer = document.getElementById('donationForm');
    const progressContainer = document.querySelector('.progress-container');

    // 2. Aplicar la clase CSS que creamos
    if (formContainer) formContainer.classList.add('form-disabled');
    if (progressContainer) progressContainer.classList.add('form-disabled');
    
    // (Opcional) Si quieres asegurar que los botones submit sigan deshabilitados
    document.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = true);
  },
  enableForm() {
    // 1. Obtener los elementos
    const formContainer = document.getElementById('donationForm');
    const progressContainer = document.querySelector('.progress-container');

    // 2. Quitar la clase CSS para que vuelvan a la normalidad
    if (formContainer) formContainer.classList.remove('form-disabled');
    if (progressContainer) progressContainer.classList.remove('form-disabled');

    // Reactivar botones
    document.querySelectorAll('button[type="submit"]').forEach(b => {
      b.disabled = false;
      b.style.opacity = '1';
    });
  },
  setupInterceptor() {
    document.getElementById('donationForm').addEventListener('submit', (e) => {
      e.preventDefault();
      if (!STATE.currentUser) {
        Notification.show('Debes iniciar sesión', 'error');
        return;
      }
      if (!STATE.canSubmitRequest || STATE.hasActiveRequest) {
        Notification.show('Ya tienes una solicitud activa', 'error');
        return;
      }
      FormSubmit.submit();
    });
  }
};
window.FormSteps = FormSteps;