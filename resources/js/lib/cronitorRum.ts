import * as Cronitor from '@cronitorio/cronitor-rum';

export function initializeCronitorRum(): void {
    const clientKey = import.meta.env.VITE_CRONITOR_CLIENT_KEY;

    if (!clientKey) {
        return;
    }

    Cronitor.load(clientKey, {
        environment: import.meta.env.PROD ? 'production' : 'development',
        trackMode: 'history',
    });
}
