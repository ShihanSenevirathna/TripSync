// Firebase Cloud Messaging Setup
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
import { getMessaging, getToken, onMessage } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging.js";

const firebaseConfig = {
    // CONFIG PLACEHOLDER: The user needs to fill this with their real Firebase Config
    apiKey: "YOUR_API_KEY",
    authDomain: "YOUR_PROJECT_ID.firebaseapp.com",
    projectId: "YOUR_PROJECT_ID",
    storageBucket: "YOUR_PROJECT_ID.appspot.com",
    messagingSenderId: "YOUR_SENDER_ID",
    appId: "YOUR_APP_ID"
};

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

// Request permission and get token
export async function setupFCM() {
    try {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            const currentToken = await getToken(messaging, {
                vapidKey: 'YOUR_VAPID_KEY' // VAPID Key from Firebase Console
            });

            if (currentToken) {
                console.log('FCM Token generated:', currentToken);
                await saveTokenToServer(currentToken);
            } else {
                console.warn('No registration token available. Request permission to generate one.');
            }
        } else {
            console.warn('Notification permission denied.');
        }
    } catch (error) {
        console.error('An error occurred while retrieving token:', error);
    }
}

async function saveTokenToServer(token) {
    try {
        const response = await fetch('/TripSync/api/update_fcm_token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token })
        });
        const data = await response.json();
        if (data.success) {
            console.log('FCM Token saved to server successfully');
        }
    } catch (err) {
        console.error('Failed to save token to server', err);
    }
}

// Handle foreground messages
onMessage(messaging, (payload) => {
    console.log('Message received. ', payload);
    const { title, body } = payload.notification;

    // Create a custom UI notification (toast)
    showToast(title, body);
});

function showToast(title, body) {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    const toast = document.createElement('div');
    toast.className = 'bg-white border-l-4 border-emerald-500 shadow-2xl rounded-r-xl p-4 mb-4 transform transition-all duration-500 translate-x-full opacity-0 animate-in slide-in-from-right-full';
    toast.innerHTML = `
    <div class="flex items-start gap-4">
      <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600">
        <i class="ri-notification-3-fill text-xl"></i>
      </div>
      <div class="flex-1">
        <h4 class="font-bold text-gray-900 text-sm">${title}</h4>
        <p class="text-xs text-gray-500 mt-1">${body}</p>
      </div>
      <button onclick="this.parentElement.parentElement.remove()" class="text-gray-300 hover:text-gray-500">
        <i class="ri-close-line text-lg"></i>
      </button>
    </div>
  `;

    toastContainer.appendChild(toast);

    // Animation
    setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
    }, 100);

    // Auto-remove after 6 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => toast.remove(), 500);
    }, 6000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'fixed bottom-4 right-4 z-[9999] w-80 max-w-[90vw]';
    document.body.appendChild(container);
    return container;
}
