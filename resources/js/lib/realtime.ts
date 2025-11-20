import type { Channel } from 'laravel-echo';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance: Echo | null = null;

const defaultScheme =
    (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'https';

const defaultPort =
    (import.meta.env.VITE_REVERB_PORT as string | undefined) ??
    (defaultScheme === 'https' ? '443' : '80');

function createEcho(): Echo | null {
    const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

    if (!key) {
        return null;
    }

    const host =
        (import.meta.env.VITE_REVERB_HOST as string | undefined) ??
        (typeof window !== 'undefined'
            ? window.location.hostname
            : '127.0.0.1');

    const scheme = defaultScheme;
    const port = Number(defaultPort);
    const forceTLS = scheme === 'https';

    const PusherConstructor = Pusher;

    if (typeof window !== 'undefined') {
        (window as typeof window & { Pusher?: typeof Pusher }).Pusher =
            PusherConstructor;
    }

    const instance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        withCredentials: true,
    });

    if (typeof window !== 'undefined') {
        (window as typeof window & { Echo?: Echo }).Echo = instance;
    }

    return instance;
}

export function useEcho(): Echo | null {
    if (!echoInstance) {
        echoInstance = createEcho();
    }

    return echoInstance;
}

export function disconnectEcho(): void {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}

export function userChannel(userId: number | string): Channel | null {
    const echo = useEcho();

    if (!echo) {
        return null;
    }

    return echo.private(`App.Models.User.${userId}`);
}
