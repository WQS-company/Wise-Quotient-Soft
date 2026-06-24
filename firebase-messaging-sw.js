// Firebase Cloud Messaging Background Service Worker
importScripts('https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.0/firebase-messaging-compat.js');

// Extract Firebase configuration options passed via the registration URL parameters
const params = new URLSearchParams(location.search);
const firebaseConfig = {
    apiKey:            params.get('apiKey') || 'AIzaSyCZJNOxStqFs_IQBXfc5f4LXcYwqsuL6J0',
    authDomain:        params.get('authDomain') || 'wise-quotient-soft-cfe28.firebaseapp.com',
    projectId:         params.get('projectId') || 'wise-quotient-soft-cfe28',
    storageBucket:     params.get('storageBucket') || 'wise-quotient-soft-cfe28.firebasestorage.app',
    messagingSenderId: params.get('messagingSenderId') || '537983634150',
    appId:             params.get('appId') || '1:537983634150:web:d3afb1a43140d4fb62c927',
    measurementId:     params.get('measurementId') || 'G-1D26Q47FLK'
};

// Initialize the Firebase app in the service worker context
firebase.initializeApp(firebaseConfig);

// Retrieve an instance of Firebase Cloud Messaging
const messaging = firebase.messaging();

// Handle background push notifications
messaging.onBackgroundMessage((payload) => {
    console.log('[firebase-messaging-sw.js] Received background message ', payload);
    const notificationTitle = payload.notification.title || "Wise Quotient Soft";
    const notificationOptions = {
        body:  payload.notification.body || "",
        icon:  payload.notification.icon || '/dashboard/wqs/LOGO W.png',
        badge: '/dashboard/wqs/LOGO W.png',
        data:  payload.data || {}
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});

// Click notification event handler to support deep linking and app redirection
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const clickAction = event.notification.data.click_action || '/dashboard/wqs/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // Check if client window is already open and focus it
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url === clickAction && 'focus' in client) {
                    return client.focus();
                }
            }
            // If no window matching URL, open a new browser tab/window
            if (clients.openWindow) {
                return clients.openWindow(clickAction);
            }
        })
    );
});
