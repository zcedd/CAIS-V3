import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';

import { ApiError } from '@/api/errors';
import { fetchMe, logout as logoutRequest } from '@/api/mobile';
import type { Department, MobileUser } from '@/api/types';
import { TOKEN_KEY, setTokenReader, setUnauthorizedHandler } from '@/api/client';
import { deleteSetting, getSetting, setSetting } from '@/db/settings';

type SessionProfile = {
    user: MobileUser;
    department: Department | null;
    permissions: string[];
};

type SessionState = {
    ready: boolean;
    token: string | null;
    user: MobileUser | null;
    department: Department | null;
    permissions: string[];
    challengeToken: string | null;
    hydrate: () => Promise<void>;
    establish: (token: string, user: MobileUser) => Promise<void>;
    refreshMe: () => Promise<void>;
    setChallenge: (challengeToken: string) => void;
    clearLocal: () => Promise<void>;
    signOut: () => Promise<void>;
};

async function saveProfile(profile: SessionProfile | null): Promise<void> {
    if (!profile) {
        await deleteSetting('session_profile');

        return;
    }

    await setSetting('session_profile', JSON.stringify(profile));
}

function readDepartment(value: unknown): Department | null {
    if (typeof value === 'string') {
        const name = value.trim();

        return name === '' ? null : { id: 0, name, slug: '' };
    }

    if (!value || typeof value !== 'object') {
        return null;
    }

    const row = value as Record<string, unknown>;

    if (typeof row.name !== 'string' || row.name.trim() === '') {
        return null;
    }

    return {
        id: typeof row.id === 'number' ? row.id : 0,
        name: row.name,
        slug: typeof row.slug === 'string' ? row.slug : '',
    };
}

function readProfile(value: string | null): SessionProfile | null {
    if (!value) {
        return null;
    }

    try {
        const parsed = JSON.parse(value) as {
            user?: Partial<MobileUser>;
            department?: unknown;
            permissions?: unknown;
        };

        if (!parsed?.user || typeof parsed.user.id !== 'number' || typeof parsed.user.name !== 'string' || typeof parsed.user.email !== 'string') {
            return null;
        }

        return {
            user: {
                id: parsed.user.id,
                name: parsed.user.name,
                email: parsed.user.email,
                department_id: typeof parsed.user.department_id === 'number' ? parsed.user.department_id : null,
            },
            department: readDepartment(parsed.department),
            permissions: Array.isArray(parsed.permissions)
                ? parsed.permissions.filter((entry): entry is string => typeof entry === 'string')
                : [],
        };
    } catch {
        return null;
    }
}

export const useSessionStore = create<SessionState>((set, get) => ({
    ready: false,
    token: null,
    user: null,
    department: null,
    permissions: [],
    challengeToken: null,
    hydrate: async () => {
        const token = await SecureStore.getItemAsync(TOKEN_KEY);
        const profile = readProfile(await getSetting('session_profile'));

        set({
            ready: true,
            token,
            user: profile?.user ?? null,
            department: profile?.department ?? null,
            permissions: profile?.permissions ?? [],
        });

        if (token) {
            try {
                await get().refreshMe();
            } catch (error) {
                if (!(error instanceof ApiError) || error.status !== 401) {
                    return;
                }
            }
        }
    },
    establish: async (token, user) => {
        await SecureStore.setItemAsync(TOKEN_KEY, token);
        await saveProfile({ user, department: null, permissions: [] });
        set({ token, user, department: null, permissions: [], challengeToken: null });

        try {
            await get().refreshMe();
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) {
                throw error;
            }
        }
    },
    refreshMe: async () => {
        const profile = await fetchMe();
        await saveProfile(profile);
        set({
            user: profile.user,
            department: profile.department,
            permissions: profile.permissions,
        });
    },
    setChallenge: (challengeToken) => set({ challengeToken }),
    clearLocal: async () => {
        await SecureStore.deleteItemAsync(TOKEN_KEY);
        await deleteSetting('session_profile');
        set({
            token: null,
            user: null,
            department: null,
            permissions: [],
            challengeToken: null,
        });
    },
    signOut: async () => {
        try {
            if (get().token) {
                await logoutRequest();
            }
        } catch {
            // Local sign-out still proceeds when the server cannot be reached.
        }

        await get().clearLocal();
    },
}));

setTokenReader(async () => useSessionStore.getState().token);
setUnauthorizedHandler(() => {
    void useSessionStore.getState().clearLocal();
});
