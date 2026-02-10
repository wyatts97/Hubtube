import { ref } from 'vue';
import { useFetch } from './useFetch';

const isSupported = ref('serviceWorker' in navigator && 'PushManager' in window);
const isSubscribed = ref(false);
const isLoading = ref(false);

export function usePushNotifications() {
    const { post, del } = useFetch();

    const checkSubscription = async () => {
        if (!isSupported.value) return;
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            isSubscribed.value = !!subscription;
        } catch {
            isSubscribed.value = false;
        }
    };

    const subscribe = async () => {
        if (!isSupported.value) return false;
        isLoading.value = true;

        try {
            const registration = await navigator.serviceWorker.ready;

            // Get VAPID public key from server
            const { ok: keyOk, data: keyData } = await post('/api/push/vapid-key');
            if (!keyOk || !keyData?.key) {
                isLoading.value = false;
                return false;
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(keyData.key),
            });

            // Send subscription to server
            const { ok } = await post('/api/push/subscribe', {
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh')))),
                    auth: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth')))),
                },
            });

            isSubscribed.value = ok;
            isLoading.value = false;
            return ok;
        } catch (e) {
            console.error('Push subscription failed:', e);
            isLoading.value = false;
            return false;
        }
    };

    const unsubscribe = async () => {
        if (!isSupported.value) return false;
        isLoading.value = true;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                await subscription.unsubscribe();
                await del('/api/push/unsubscribe', { endpoint: subscription.endpoint }, {});
            }

            isSubscribed.value = false;
            isLoading.value = false;
            return true;
        } catch (e) {
            console.error('Push unsubscribe failed:', e);
            isLoading.value = false;
            return false;
        }
    };

    const toggle = async () => {
        if (isSubscribed.value) {
            return await unsubscribe();
        } else {
            return await subscribe();
        }
    };

    return {
        isSupported,
        isSubscribed,
        isLoading,
        checkSubscription,
        subscribe,
        unsubscribe,
        toggle,
    };
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}
