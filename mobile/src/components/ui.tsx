import { forwardRef, type ReactNode } from 'react';
import { Pressable, ScrollView, Text, TextInput, View, type ScrollViewInstance } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import type { ItemStatus } from '@/domain/status';
import { statusLabel } from '@/domain/status';

export function Screen({
    title,
    subtitle,
    children,
    footer,
    onBack,
}: {
    title: string;
    subtitle?: string;
    children: ReactNode;
    footer?: ReactNode;
    onBack?: () => void;
}) {
    return (
        <SafeAreaView className="flex-1 bg-stone-100">
            <View className="gap-2 border-b border-stone-800 bg-stone-900 px-4 py-4">
                {onBack ? (
                    <Pressable accessibilityRole="button" className="min-h-12 justify-center" onPress={onBack}>
                        <Text className="text-base font-semibold text-emerald-200">Back</Text>
                    </Pressable>
                ) : null}
                <Text className="text-2xl font-semibold text-white">{title}</Text>
                {subtitle ? <Text className="text-base text-stone-300">{subtitle}</Text> : null}
            </View>
            <View className="flex-1">{children}</View>
            {footer ? <View className="border-t border-stone-200 bg-white px-4 py-3">{footer}</View> : null}
        </SafeAreaView>
    );
}

export const Body = forwardRef<ScrollViewInstance, { children: ReactNode; scrollEnabled?: boolean }>(function Body(
    { children, scrollEnabled = true },
    ref,
) {
    return (
        <ScrollView
            ref={ref}
            className="flex-1"
            contentContainerClassName="gap-4 p-4 pb-10"
            keyboardShouldPersistTaps="handled"
            nestedScrollEnabled
            scrollEnabled={scrollEnabled}
        >
            {children}
        </ScrollView>
    );
});

export function Card({ children }: { children: ReactNode }) {
    return <View className="gap-3 rounded-2xl bg-white p-4">{children}</View>;
}

export function Button({
    label,
    onPress,
    variant = 'primary',
    disabled = false,
}: {
    label: string;
    onPress: () => void;
    variant?: 'primary' | 'secondary' | 'danger';
    disabled?: boolean;
}) {
    const surface =
        variant === 'primary'
            ? 'bg-emerald-800'
            : variant === 'danger'
              ? 'bg-red-800'
              : 'border border-stone-300 bg-white';
    const ink = variant === 'secondary' ? 'text-stone-900' : 'text-white';

    return (
        <Pressable
            accessibilityRole="button"
            disabled={disabled}
            onPress={onPress}
            className={`min-h-14 items-center justify-center rounded-xl px-4 ${surface} ${disabled ? 'opacity-50' : ''}`}
        >
            <Text className={`text-lg font-semibold ${ink}`}>{label}</Text>
        </Pressable>
    );
}

export function Field({
    label,
    value,
    onChangeText,
    error,
    placeholder,
    secureTextEntry = false,
    keyboardType = 'default',
    autoCapitalize = 'sentences',
    multiline = false,
}: {
    label: string;
    value: string;
    onChangeText: (value: string) => void;
    error?: string;
    placeholder?: string;
    secureTextEntry?: boolean;
    keyboardType?: 'default' | 'email-address' | 'number-pad';
    autoCapitalize?: 'none' | 'sentences' | 'words';
    multiline?: boolean;
}) {
    return (
        <View className="gap-2">
            <Text className="text-base font-semibold text-stone-800">{label}</Text>
            <TextInput
                value={value}
                onChangeText={onChangeText}
                placeholder={placeholder}
                placeholderTextColor="#78716c"
                secureTextEntry={secureTextEntry}
                keyboardType={keyboardType}
                autoCapitalize={autoCapitalize}
                autoCorrect={autoCapitalize === 'none' ? false : undefined}
                multiline={multiline}
                className={`rounded-xl border bg-white px-4 text-lg text-stone-900 ${
                    multiline ? 'min-h-32 py-3' : 'min-h-14'
                } ${error ? 'border-red-600' : 'border-stone-300'}`}
            />
            {error ? <Text className="text-base text-red-700">{error}</Text> : null}
        </View>
    );
}

export function Choice({
    label,
    selected,
    onPress,
}: {
    label: string;
    selected: boolean;
    onPress: () => void;
}) {
    return (
        <Pressable
            accessibilityRole="button"
            accessibilityState={{ selected }}
            onPress={onPress}
            className={`min-h-14 justify-center rounded-xl border px-4 ${
                selected ? 'border-emerald-800 bg-emerald-50' : 'border-stone-300 bg-white'
            }`}
        >
            <Text className={`text-lg ${selected ? 'font-semibold text-emerald-950' : 'text-stone-900'}`}>{label}</Text>
        </Pressable>
    );
}

export function CheckRow({
    label,
    checked,
    onPress,
    error,
}: {
    label: string;
    checked: boolean;
    onPress: () => void;
    error?: string;
}) {
    return (
        <View className="gap-2">
            <Pressable accessibilityRole="checkbox" accessibilityState={{ checked }} onPress={onPress} className="min-h-14 flex-row items-center gap-3">
                <View className={`h-7 w-7 items-center justify-center rounded-md border ${checked ? 'border-emerald-800 bg-emerald-800' : 'border-stone-400 bg-white'}`}>
                    {checked ? <Text className="text-base font-bold text-white">✓</Text> : null}
                </View>
                <Text className="flex-1 text-lg text-stone-900">{label}</Text>
            </Pressable>
            {error ? <Text className="text-base text-red-700">{error}</Text> : null}
        </View>
    );
}

export function Notice({ tone = 'info', children }: { tone?: 'info' | 'error' | 'warning'; children: string }) {
    const surface = tone === 'error' ? 'bg-red-100' : tone === 'warning' ? 'bg-amber-100' : 'bg-stone-200';
    const ink = tone === 'error' ? 'text-red-900' : tone === 'warning' ? 'text-amber-950' : 'text-stone-800';

    return (
        <View className={`rounded-2xl p-4 ${surface}`}>
            <Text className={`text-base ${ink}`}>{children}</Text>
        </View>
    );
}

const STATUS_TONE: Record<ItemStatus, string> = {
    pending: 'bg-stone-200 text-stone-800',
    pending_sync: 'bg-amber-200 text-amber-950',
    served: 'bg-emerald-200 text-emerald-950',
    partial: 'bg-sky-200 text-sky-950',
    absent: 'bg-stone-300 text-stone-800',
    excluded: 'bg-stone-300 text-stone-700',
    conflict: 'bg-red-200 text-red-950',
};

export function StatusPill({ status }: { status: ItemStatus }) {
    return (
        <View className={`self-start rounded-full px-3 py-1 ${STATUS_TONE[status]}`}>
            <Text className="text-base font-semibold">{statusLabel(status)}</Text>
        </View>
    );
}

export function EmptyState({ title, body }: { title: string; body: string }) {
    return (
        <View className="gap-2 rounded-2xl border border-dashed border-stone-300 bg-white p-5">
            <Text className="text-xl font-semibold text-stone-900">{title}</Text>
            <Text className="text-base text-stone-600">{body}</Text>
        </View>
    );
}
