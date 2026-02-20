const Loader = {
  show(message = 'Cargando...') {
    let loader = document.getElementById('loading-overlay');
    if (!loader) {
      loader = document.createElement('div');
      loader.id = 'loading-overlay';
      loader.innerHTML = `<div class="spinner"></div><p>${message}</p>`;
      loader.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;';
      document.body.appendChild(loader);
    } else {
      loader.querySelector('p').textContent = message;
      loader.style.display = 'flex';
    }
  },
  hide() {
    const loader = document.getElementById('loading-overlay');
    if (loader) loader.style.display = 'none';
  }
};
window.Loader = Loader;
