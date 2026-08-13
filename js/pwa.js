// js/pwa.js - Progressive Web App (PWA) Handler for Secure Locker

let deferredPrompt = null;

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then((registration) => {
                console.log('Secure Locker PWA ServiceWorker registered with scope:', registration.scope);
            })
            .catch((error) => {
                console.log('Secure Locker PWA ServiceWorker registration failed:', error);
            });
    });
}

// Capture BeforeInstallPrompt
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show PWA install button if present in UI
    const installBtns = document.querySelectorAll('.pwa-install-btn');
    installBtns.forEach(btn => {
        btn.style.display = 'inline-flex';
    });
});

// Function to trigger PWA installation
window.installPWA = async function() {
    if (!deferredPrompt) {
        alert('App installation is available via your browser menu (e.g. "Add to Home screen" or "Install App").');
        return;
    }

    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    console.log(`PWA install user choice: ${outcome}`);
    deferredPrompt = null;

    const installBtns = document.querySelectorAll('.pwa-install-btn');
    installBtns.forEach(btn => {
        btn.style.display = 'none';
    });
};

// Track installation completion
window.addEventListener('appinstalled', () => {
    console.log('Secure Locker PWA was successfully installed.');
    deferredPrompt = null;
});
