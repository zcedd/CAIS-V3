import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

interface EverifyLivenessSdkResult {
    status: 'COMPLETED' | 'CANCELLED';
    result?: {
        session_id?: string;
        photo?: string;
        photo_url?: string;
    };
}

interface Window {
    eKYC?: () => {
        start: (options: { pubKey: string }) => Promise<EverifyLivenessSdkResult>;
    };
}
