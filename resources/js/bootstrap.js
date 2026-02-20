import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Suppress Pusher connection errors from logging to console (PageSpeed: "Browser errors logged to console")
Pusher.logToConsole = false;

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST;

// Only initialize Echo if Reverb is configured (prevents WebSocket errors in production)
if (reverbKey && reverbHost && reverbHost !== 'localhost') {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    // Provide a no-op Echo stub so channel subscriptions don't throw
    window.Echo = {
        channel: () => ({ listen: () => ({}), stopListening: () => ({}) }),
        private: () => ({ listen: () => ({}), stopListening: () => ({}) }),
        join: () => ({ here: () => ({}), joining: () => ({}), leaving: () => ({}), listen: () => ({}) }),
        leave: () => {},
        leaveChannel: () => {},
        listen: () => ({}),
        connector: { pusher: null },
    };
}
