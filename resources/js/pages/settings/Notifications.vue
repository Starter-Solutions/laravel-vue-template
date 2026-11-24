<script setup lang="ts">
import { Head, useForm, useHttp } from '@inertiajs/vue3';
import { Button } from '@starter-solutions/vue-ui/base/button';
import { Label } from '@starter-solutions/vue-ui/base/label';
import { Switch } from '@starter-solutions/vue-ui/base/switch';
import { onMounted, onUnmounted, ref, shallowRef } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { edit, test, update } from '@/routes/notifications';
import { current, destroy, store } from '@/routes/notifications/subscription';

type SubscriptionDetails = {
    endpoint: string;
    keys: { p256dh: string; auth: string };
};

const props = defineProps<{
    enabled: boolean;
    vapidPublicKey: string | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifications', href: edit() }] },
});

const permission = ref<NotificationPermission>('default');
const deviceSupportsPush = ref(false);
const preference = useForm({ enabled: props.enabled });
const lookup = useHttp<SubscriptionDetails, { id: number | null }>({
    endpoint: '',
    keys: { p256dh: '', auth: '' },
});
const registration = useHttp<SubscriptionDetails, { id: number }>({
    endpoint: '',
    keys: { p256dh: '', auth: '' },
});
const removal = useHttp<Record<string, never>>({});
const push = useHttp<
    Record<string, never>,
    { sent: number; failed: number; expired: number[] }
>({});
const currentDevice = shallowRef<{
    id: number;
    subscription: PushSubscription;
} | null>(null);
const checking = ref(false);
const updatingDevice = ref(false);
const testing = ref(false);
const deviceStatus = ref('');
const testStatus = ref('');
const retrySeconds = ref(0);
let retryTimer: number | undefined;
const requestOptions = {
    onHttpException: () => false,
    onNetworkError: () => false,
};

function supportsPush() {
    return (
        'Notification' in window &&
        'serviceWorker' in navigator &&
        'PushManager' in window
    );
}

function syncPermission() {
    deviceSupportsPush.value = supportsPush();
    const currentPermission =
        'Notification' in window ? Notification.permission : 'default';

    if (currentPermission !== permission.value) {
        permission.value = currentPermission;
        currentDevice.value = null;
        deviceStatus.value = '';
    }
}

function subscriptionKeys(subscription: PushSubscription) {
    const keys = subscription.toJSON().keys;

    if (!keys?.p256dh || !keys.auth) {
        throw new Error('Push subscription keys are unavailable.');
    }

    return { p256dh: keys.p256dh, auth: keys.auth };
}

async function refreshDevice() {
    if (
        document.visibilityState === 'hidden' ||
        checking.value ||
        updatingDevice.value ||
        testing.value
    ) {
        return;
    }

    checking.value = true;
    syncPermission();

    try {
        if (!deviceSupportsPush.value || permission.value !== 'granted') {
            currentDevice.value = null;
            return;
        }

        const worker = await navigator.serviceWorker.getRegistration();
        const subscription = await worker?.pushManager.getSubscription();

        if (!subscription) {
            currentDevice.value = null;
            return;
        }

        lookup.endpoint = subscription.endpoint;
        lookup.keys = subscriptionKeys(subscription);
        const { id } = await lookup.submit(current(), requestOptions);
        currentDevice.value = id === null ? null : { id, subscription };
    } catch {
        deviceStatus.value = 'Could not check this device. Try again.';
    } finally {
        checking.value = false;
    }
}

onMounted(() => {
    refreshDevice();
    window.addEventListener('focus', refreshDevice);
    document.addEventListener('visibilitychange', refreshDevice);
});

onUnmounted(() => {
    window.removeEventListener('focus', refreshDevice);
    document.removeEventListener('visibilitychange', refreshDevice);
    window.clearTimeout(retryTimer);
    push.cancel();
});

async function getPushSubscription(vapidPublicKey: string) {
    await navigator.serviceWorker.register('/service-worker.js');
    const { pushManager } = await navigator.serviceWorker.ready;
    const existing = await pushManager.getSubscription();

    if (existing) return existing;

    const encodedKey = vapidPublicKey.replace(/-/g, '+').replace(/_/g, '/');
    const options = {
        userVisibleOnly: true,
        applicationServerKey: Uint8Array.from(atob(encodedKey), (byte) =>
            byte.charCodeAt(0),
        ),
    };

    let timeout: number | undefined;

    try {
        return await Promise.race([
            pushManager.subscribe(options),
            new Promise<null>((resolve) => {
                timeout = window.setTimeout(() => resolve(null), 15000);
            }),
        ]);
    } finally {
        window.clearTimeout(timeout);
    }
}

function savePreference(enabled: boolean) {
    testStatus.value = '';
    preference.enabled = enabled;
    const saveFailed = () => {
        preference.reset('enabled');
        preference.setError('enabled', 'Could not save. Try again.');
        return false;
    };

    preference.submit(update(), {
        preserveScroll: true,
        onCancel: () => {
            preference.reset('enabled');
        },
        onError: saveFailed,
        onHttpException: saveFailed,
        onNetworkError: saveFailed,
    });
}

async function toggleDevice(enabled: boolean) {
    if (checking.value || updatingDevice.value || testing.value) return;

    updatingDevice.value = true;
    deviceStatus.value = '';

    try {
        if (!enabled) {
            const device = currentDevice.value;
            if (!device) return;

            await removal.submit(destroy(device.id), requestOptions);
            currentDevice.value = null;
            await device.subscription.unsubscribe().catch(() => false);
            return;
        }

        if (!props.vapidPublicKey || !deviceSupportsPush.value) return;
        syncPermission();

        if (permission.value === 'default') {
            permission.value = await Notification.requestPermission();
        }

        if (permission.value !== 'granted') {
            if (permission.value === 'default') {
                deviceStatus.value =
                    'Allow notifications to connect this device.';
            }
            return;
        }

        const subscription = await getPushSubscription(props.vapidPublicKey);

        if (!subscription) {
            deviceStatus.value = 'Could not connect this device. Try again.';
            return;
        }

        registration.endpoint = subscription.endpoint;
        registration.keys = subscriptionKeys(subscription);
        const { id } = await registration.submit(store(), requestOptions);
        currentDevice.value = { id, subscription };
    } catch {
        deviceStatus.value = enabled
            ? 'Could not connect this device. Try again.'
            : 'Could not disconnect this device. Try again.';
    } finally {
        updatingDevice.value = false;
    }
}

function startTestCountdown(seconds: number) {
    const retryAt = Date.now() + seconds * 1000;
    testStatus.value =
        'Too many test pushes. Wait for the countdown to finish.';

    function updateCountdown() {
        const remaining = retryAt - Date.now();
        retrySeconds.value = Math.max(0, Math.ceil(remaining / 1000));

        if (remaining > 0) {
            retryTimer = window.setTimeout(
                updateCountdown,
                remaining % 1000 || 1000,
            );
        } else {
            testStatus.value = 'You can send a test push again.';
        }
    }

    updateCountdown();
}

async function sendTest() {
    if (!preference.enabled || !props.vapidPublicKey || retrySeconds.value > 0)
        return;

    testing.value = true;
    testStatus.value = '';
    try {
        const { sent, failed, expired } = await push.submit(test(), {
            onHttpException: ({ status, headers }) => {
                switch (status) {
                    case 429: {
                        const seconds = Number(headers['retry-after']);
                        if (Number.isFinite(seconds) && seconds > 0) {
                            startTestCountdown(seconds);
                        } else {
                            testStatus.value =
                                'Too many test pushes. Wait a moment and try again.';
                        }
                        break;
                    }
                    case 401:
                    case 419:
                        testStatus.value =
                            'Your session expired. Reload this page and sign in if prompted.';
                        break;
                    case 403:
                        testStatus.value =
                            'The server refused this test. Check that push is enabled for your account.';
                        break;
                    case 503:
                        testStatus.value =
                            'Web Push is temporarily unavailable on the server.';
                        break;
                    case 504:
                        testStatus.value =
                            'The server timed out. Some devices may still receive the test.';
                        break;
                    default:
                        testStatus.value = `The test request failed (HTTP ${status}).`;
                }
                return false;
            },
            onNetworkError: () => {
                testStatus.value =
                    'No usable response from the server. Check your connection and try again.';
                return false;
            },
        });

        if (currentDevice.value && expired.includes(currentDevice.value.id)) {
            await currentDevice.value.subscription
                .unsubscribe()
                .catch(() => false);
            currentDevice.value = null;
        }

        if (sent > 0) {
            testStatus.value = `Push services accepted the test for ${sent} ${sent === 1 ? 'device' : 'devices'}.`;
            if (failed > 0) {
                testStatus.value += ` Failed on ${failed}.`;
            }
        } else if (failed > 0) {
            testStatus.value = 'No push service accepted the test.';
        } else {
            testStatus.value = 'No devices connected to this account.';
        }
    } catch {
        testStatus.value ||= 'Could not send the test push.';
    } finally {
        testing.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <Head title="Notifications" />
        <Heading variant="small" title="Notifications" />

        <div class="space-y-2">
            <div class="flex items-center justify-between gap-4">
                <Label for="push-enabled"
                    >Allow push notifications for this account</Label
                >
                <Switch
                    id="push-enabled"
                    aria-describedby="push-account-description"
                    :model-value="preference.enabled"
                    :disabled="preference.processing"
                    @update:model-value="savePreference"
                />
            </div>
            <p
                id="push-account-description"
                class="text-sm text-muted-foreground"
            >
                Controls push on all devices connected to this account.
            </p>
            <InputError role="alert" :message="preference.errors.enabled" />
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between gap-4">
                <Label for="push-device-enabled">Connect this device</Label>
                <Switch
                    id="push-device-enabled"
                    aria-describedby="push-device-description"
                    :model-value="currentDevice !== null"
                    :disabled="
                        checking ||
                        updatingDevice ||
                        testing ||
                        !deviceSupportsPush ||
                        permission === 'denied' ||
                        (!vapidPublicKey && currentDevice === null)
                    "
                    @update:model-value="toggleDevice"
                />
            </div>
            <p
                id="push-device-description"
                class="text-sm text-muted-foreground"
            >
                <template v-if="!deviceSupportsPush">
                    Push is unavailable here. On iPhone or iPad, open the app
                    from the Home Screen.
                </template>
                <template v-else-if="permission === 'denied'">
                    Allow notifications in your device or browser settings.
                </template>
                <template v-else>
                    Connect this device to receive push when enabled for your
                    account.
                </template>
            </p>
            <p
                role="status"
                class="text-sm"
                :class="{ 'sr-only': !deviceStatus }"
            >
                {{ deviceStatus }}
            </p>
        </div>

        <div class="space-y-2">
            <Button
                type="button"
                variant="outline"
                aria-describedby="push-test-description"
                :disabled="
                    !preference.enabled ||
                    !vapidPublicKey ||
                    preference.processing ||
                    checking ||
                    updatingDevice ||
                    testing ||
                    retrySeconds > 0
                "
                @click="sendTest"
            >
                {{ testing ? 'Sending…' : 'Send test push' }}
            </Button>
            <p id="push-test-description" class="text-sm text-muted-foreground">
                {{
                    vapidPublicKey
                        ? 'Sends to all connected devices on this account.'
                        : 'Web Push is not configured.'
                }}
            </p>
            <p v-if="retrySeconds > 0" aria-hidden="true" class="text-sm">
                Too many test pushes. Try again in {{ retrySeconds }}
                {{ retrySeconds === 1 ? 'second' : 'seconds' }}.
            </p>
            <p
                role="status"
                class="text-sm"
                :class="{ 'sr-only': !testStatus || retrySeconds > 0 }"
            >
                {{ testStatus }}
            </p>
        </div>
    </div>
</template>
