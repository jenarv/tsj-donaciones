document.addEventListener('DOMContentLoaded', async function() {
  Progress.init();
  FormSteps.init();
  await Auth.init();
});
