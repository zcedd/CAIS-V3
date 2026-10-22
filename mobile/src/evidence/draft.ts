import { create } from 'zustand';

type EvidenceDraft = {
    itemKey: string | null;
    photoUri: string | null;
    signatureData: string | null;
    begin: (itemKey: string) => void;
    setPhotoUri: (uri: string | null) => void;
    setSignatureData: (data: string | null) => void;
    end: (itemKey: string) => void;
};

export const useEvidenceDraft = create<EvidenceDraft>((set, get) => ({
    itemKey: null,
    photoUri: null,
    signatureData: null,
    begin: (itemKey) => {
        if (get().itemKey === itemKey) {
            return;
        }

        set({ itemKey, photoUri: null, signatureData: null });
    },
    setPhotoUri: (photoUri) => set({ photoUri }),
    setSignatureData: (signatureData) => set({ signatureData }),
    end: (itemKey) => {
        if (get().itemKey !== itemKey) {
            return;
        }

        set({ itemKey: null, photoUri: null, signatureData: null });
    },
}));
