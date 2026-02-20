const FormSubmit = {
  async submit() {
    if (!FormSteps.validate(3)) return;
    const formData = {
      email: document.getElementById('email').value.trim(),
      carrera: document.getElementById('carrera').value,
      id_paquete: document.getElementById('paquete').value,
      nombreAlumno: document.getElementById('nombreAlumno').value.trim(),
      numeroControl: document.getElementById('numeroControl').value.trim(),
      correoInstitucional: document.getElementById('correoInstitucional').value.trim(),
      tipo_donacion: Utils.getCategoryName(STATE.currentCategory)
    };
    try {
      Loader.show('Enviando...');
      const data = await Utils.apiRequest('/solicitud/enviar.php', {
        method: 'POST',
        body: JSON.stringify(formData)
      });
      if (!data.success) throw new Error(data.error);
      document.querySelector('.form-step[data-step="3"]').classList.remove('active');
      document.querySelector('.form-step[data-step="4"]').classList.add('active');
      STATE.currentStep = 4;
      Progress.update(4);
      window.scrollTo({top:0,behavior:'smooth'});
    } catch (error) {
      console.error('Error:', error);
      Notification.show(error.message, 'error');
    } finally {
      Loader.hide();
    }
  }
};
window.FormSubmit = FormSubmit;
