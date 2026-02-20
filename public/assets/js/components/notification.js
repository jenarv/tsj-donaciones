const Notification = {
  show(message, type = 'info') {
    const notification = Utils.createElement('div', {
      className: `notification notification-${type}`
    }, [
      Utils.createElement('span', {}, [message]),
      Utils.createElement('button', {
        onClick: function() { this.parentElement.remove(); }
      }, ['×'])
    ]);
    
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
  }
};
window.Notification = Notification;
