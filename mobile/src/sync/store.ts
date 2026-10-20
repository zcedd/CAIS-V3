import { create } from 'zustand';

type SyncState = {
    online: boolean | null;
    draining: boolean;
    pendingCount: number;
    conflictCount: number;
    setOnline: (online: boolean) => void;
    setDraining: (draining: boolean) => void;
    setCounts: (pendingCount: number, conflictCount: number) => void;
};

export const useSyncStore = create<SyncState>((set) => ({
    online: null,
    draining: false,
    pendingCount: 0,
    conflictCount: 0,
    setOnline: (online) => set({ online }),
    setDraining: (draining) => set({ draining }),
    setCounts: (pendingCount, conflictCount) => set({ pendingCount, conflictCount }),
}));
