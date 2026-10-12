import type { LucideIcon } from 'lucide-react';
import { useEffect, useSyncExternalStore } from 'react';

export type PageCommand = {
    id: string;
    label: string;
    icon?: LucideIcon;
    keywords?: string;
    onSelect: () => void;
};

const registry = new Map<string, PageCommand>();
const listeners = new Set<() => void>();
let snapshot: PageCommand[] = [];
const emptySnapshot: PageCommand[] = [];

function publish(): void {
    snapshot = Array.from(registry.values());
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function useRegisteredCommands(): PageCommand[] {
    return useSyncExternalStore(
        subscribe,
        () => snapshot,
        () => emptySnapshot,
    );
}

export function usePageCommands(commands: PageCommand[]): void {
    useEffect(() => {
        for (const command of commands) {
            registry.set(command.id, command);
        }

        publish();

        return () => {
            for (const command of commands) {
                if (registry.get(command.id) === command) {
                    registry.delete(command.id);
                }
            }

            publish();
        };
    }, [commands]);
}
