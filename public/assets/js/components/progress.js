/**
 * PROGRESS COMPONENT
 * Handles progress indicator
 */

const Progress = {
  /**
   * Initialize progress indicator
   */
  init() {
    this.render();
    this.update(STATE.currentStep);
  },

  /**
   * Render progress steps
   */
  render() {
    const container = document.getElementById('progress-steps');
    container.innerHTML = '';

    CONFIG.STEPS.forEach((step, index) => {
      const stepEl = Utils.createElement('div', {
        className: 'step',
        dataset: { step: step.id }
      }, [
        Utils.createElement('div', { className: 'step-number' }, [step.id.toString()]),
        Utils.createElement('div', { className: 'step-label' }, [step.label]),
        Utils.createElement('div', { className: 'step-line' })
      ]);

      container.appendChild(stepEl);
    });
  },

  /**
   * Update progress indicator
   */
  update(currentStep) {
    document.querySelectorAll('.step').forEach((step, index) => {
      const stepNumber = index + 1;
      if (stepNumber < currentStep) {
        step.classList.add('completed');
        step.classList.remove('active');
      } else if (stepNumber === currentStep) {
        step.classList.add('active');
        step.classList.remove('completed');
      } else {
        step.classList.remove('active', 'completed');
      }
    });
  }
};

window.Progress = Progress;
