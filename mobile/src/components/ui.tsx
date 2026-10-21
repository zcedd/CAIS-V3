import { forwardRef, type ReactNode } from 'react';
import { KeyboardAvoidingView, Pressable, ScrollView, Text, TextInput, View, type ScrollViewInstance } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import type { ItemStatus } from '@/domain/status';
import { statusLabel } from '@/domain/status';

const PLACEHOLDER = '#78716c';

const CARD_SHADOW = {
    borderCurve: 'continuous' as const,
    shadowColor: '#1C1917',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.05,
    shadowRadius: 12,
    elevation: 1,
};

export function Screen({
    kicker,
    title,
    subtitle,
    children,
    footer,
    onBack,
    action,
    compact = false,
}: {
    kicker?: string;
    title: string;
    subtitle?: string;
    children: ReactNode;
    footer?: ReactNode;
    onBack?: () => void;
    action?: ReactNode;
    compact?: boolean;
}) {
    return (
        <SafeAreaView className="flex-1 bg-paper">
            <View className={`gap-2 px-6 ${compact ? 'pb-2 pt-2' : 'pb-3 pt-4'}`}>
                {onBack || action ? (
                    <View className="min-h-11 flex-row items-center justify-between">
                        {onBack ? (
                            <Pressable accessibilityRole="button" onPress={onBack} className="min-h-11 justify-center pr-4 active:opacity-70">
                                <Text className="text-base font-semibold text-brand">Back</Text>
                            </Pressable>
                        ) : (
                            <View />
                        )}
                        {action}
                    </View>
                ) : null}
                {kicker ? <Text className="text-base font-semibold text-brand">{kicker}</Text> : null}
                <Text accessibilityRole="header" className={`${compact ? 'text-xl' : 'text-3xl'} font-semibold tracking-tight text-ink`}>
                    {title}
                </Text>
                {subtitle ? <Text className="text-lg text-ink/70">{subtitle}</Text> : null}
            </View>
            <KeyboardAvoidingView behavior={process.env.EXPO_OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
                <View className="flex-1">{children}</View>
                {footer ? <View className="border-t border-black/5 bg-white px-6 py-4">{footer}</View> : null}
            </KeyboardAvoidingView>
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
            contentContainerClassName="gap-8 px-6 pb-8 pt-2"
            keyboardShouldPersistTaps="handled"
            nestedScrollEnabled
            scrollEnabled={scrollEnabled}
        >
            {children}
        </ScrollView>
    );
});

export function SectionTitle({ children }: { children: string }) {
    return <Text className="text-xl font-semibold text-ink">{children}</Text>;
}

export function Card({ children, tone = 'default' }: { children: ReactNode; tone?: 'default' | 'success' | 'danger' }) {
    const surface = tone === 'success' ? 'bg-emerald-50' : tone === 'danger' ? 'bg-red-50' : 'bg-white';

    return (
        <View className={`gap-4 rounded-3xl p-6 ${surface}`} style={tone === 'default' ? CARD_SHADOW : { borderCurve: 'continuous' }}>
            {children}
        </View>
    );
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
        variant === 'primary' ? 'bg-brand active:opacity-90' : variant === 'danger' ? 'bg-red-700 active:opacity-90' : 'bg-brand/10 active:bg-brand/20';
    const ink = variant === 'secondary' ? 'text-brand' : 'text-white';

    return (
        <Pressable
            accessibilityRole="button"
            accessibilityState={{ disabled }}
            disabled={disabled}
            onPress={onPress}
            android_ripple={{ color: variant === 'secondary' ? 'rgba(67, 56, 202, 0.12)' : 'rgba(255, 255, 255, 0.2)' }}
            className={`min-h-14 items-center justify-center rounded-2xl px-4 ${surface} ${disabled ? 'opacity-40' : ''}`}
            style={{ borderCurve: 'continuous' }}
        >
            <Text className={`text-center text-lg font-semibold ${ink}`}>{label}</Text>
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
            <Text className="text-base text-ink/60">{label}</Text>
            <TextInput
                value={value}
                onChangeText={onChangeText}
                placeholder={placeholder}
                placeholderTextColor={PLACEHOLDER}
                secureTextEntry={secureTextEntry}
                keyboardType={keyboardType}
                autoCapitalize={autoCapitalize}
                autoCorrect={autoCapitalize === 'none' ? false : undefined}
                multiline={multiline}
                textAlignVertical={multiline ? 'top' : 'center'}
                accessibilityLabel={label}
                className={`rounded-2xl border bg-white px-4 text-lg text-ink ${multiline ? 'min-h-32 py-3' : 'min-h-14'} ${
                    error ? 'border-red-600' : 'border-black/10'
                }`}
                style={{ borderCurve: 'continuous' }}
            />
            {error ? <Text className="text-base text-red-800">{error}</Text> : null}
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
            className={`min-h-14 flex-row items-center gap-3 rounded-2xl px-4 ${selected ? 'bg-brand/10' : 'bg-white'}`}
            style={{ borderCurve: 'continuous' }}
        >
            <View className={`h-6 w-6 rounded-full border-2 ${selected ? 'border-brand bg-brand' : 'border-black/20 bg-white'}`} />
            <Text className={`flex-1 text-lg ${selected ? 'font-semibold text-ink' : 'text-ink/80'}`}>{label}</Text>
        </Pressable>
    );
}

export function Segmented<T extends string>({
    value,
    options,
    onChange,
}: {
    value: T;
    options: readonly { value: T; label: string }[];
    onChange: (value: T) => void;
}) {
    return (
        <View className="flex-row rounded-2xl bg-black/5 p-1" style={{ borderCurve: 'continuous' }}>
            {options.map((option) => {
                const selected = option.value === value;

                return (
                    <Pressable
                        key={option.value}
                        accessibilityRole="button"
                        accessibilityState={{ selected }}
                        onPress={() => onChange(option.value)}
                        className={`min-h-12 flex-1 items-center justify-center rounded-xl px-3 ${selected ? 'bg-white' : ''}`}
                        style={{ borderCurve: 'continuous' }}
                    >
                        <Text className={`text-center text-base ${selected ? 'font-semibold text-ink' : 'text-ink/60'}`}>{option.label}</Text>
                    </Pressable>
                );
            })}
        </View>
    );
}

export function StepTrack({ step, total, label }: { step: number; total: number; label: string }) {
    return (
        <View className="gap-3">
            <View className="flex-row gap-2">
                {Array.from({ length: total }, (_, index) => (
                    <View key={index} className={`h-2 flex-1 rounded-full ${index <= step ? 'bg-brand' : 'bg-black/10'}`} />
                ))}
            </View>
            <View className="gap-1">
                <Text className="text-xl font-semibold text-ink">{label}</Text>
                <Text className="text-base text-ink/60">
                    Step {step + 1} of {total}
                </Text>
            </View>
        </View>
    );
}

export function Chip({
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
            className={`min-h-12 items-center justify-center rounded-full px-4 ${selected ? 'bg-brand' : 'bg-white'}`}
            style={{ borderCurve: 'continuous' }}
        >
            <Text className={`text-base font-semibold ${selected ? 'text-white' : 'text-ink'}`}>{label}</Text>
        </Pressable>
    );
}

export function Meter({ value, total, caption }: { value: number; total: number; caption: string }) {
    const percent = total === 0 ? 0 : Math.max(value > 0 ? 4 : 0, Math.round((value / total) * 100));

    return (
        <View className="gap-3 rounded-3xl bg-white p-6" style={CARD_SHADOW}>
            <View className="flex-row items-baseline gap-2">
                <Text className="text-3xl font-semibold text-ink">{value}</Text>
                <Text className="text-lg text-ink/60">of {total} recorded</Text>
            </View>
            <View className="h-2 overflow-hidden rounded-full bg-black/10">
                <View className="h-full rounded-full bg-brand" style={{ width: `${percent}%` }} />
            </View>
            <Text className="text-base text-ink/60">{caption}</Text>
        </View>
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
                <View className={`h-7 w-7 items-center justify-center rounded-lg ${checked ? 'bg-brand' : 'border border-black/20 bg-white'}`}>
                    {checked ? <Text className="text-base font-semibold text-white">✓</Text> : null}
                </View>
                <Text className="flex-1 text-lg text-ink">{label}</Text>
            </Pressable>
            {error ? <Text className="text-base text-red-800">{error}</Text> : null}
        </View>
    );
}

export function Notice({ tone = 'info', children }: { tone?: 'info' | 'error' | 'warning'; children: string }) {
    const surface = tone === 'error' ? 'bg-red-50' : tone === 'warning' ? 'bg-amber-50' : 'bg-brand/10';
    const ink = tone === 'error' ? 'text-red-950' : tone === 'warning' ? 'text-amber-950' : 'text-ink';
    const bar = tone === 'error' ? 'bg-red-600' : tone === 'warning' ? 'bg-amber-500' : 'bg-brand';

    return (
        <View className={`flex-row overflow-hidden rounded-2xl ${surface}`} style={{ borderCurve: 'continuous' }}>
            <View className={`w-1 ${bar}`} />
            <Text className={`flex-1 px-4 py-4 text-lg ${ink}`}>{children}</Text>
        </View>
    );
}

const STATUS_TONE: Record<ItemStatus, { surface: string; ink: string }> = {
    pending: { surface: 'bg-stone-200', ink: 'text-ink' },
    pending_sync: { surface: 'bg-amber-100', ink: 'text-amber-950' },
    served: { surface: 'bg-emerald-100', ink: 'text-emerald-950' },
    partial: { surface: 'bg-sky-100', ink: 'text-sky-950' },
    absent: { surface: 'bg-stone-200', ink: 'text-ink/70' },
    excluded: { surface: 'bg-stone-200', ink: 'text-ink/60' },
    conflict: { surface: 'bg-red-100', ink: 'text-red-950' },
};

export function StatusPill({ status }: { status: ItemStatus }) {
    const tone = STATUS_TONE[status];

    return (
        <View className={`self-start rounded-full px-3 py-1 ${tone.surface}`}>
            <Text className={`text-base font-semibold ${tone.ink}`}>{statusLabel(status)}</Text>
        </View>
    );
}

export function EmptyState({ title, body }: { title: string; body: string }) {
    return (
        <View className="items-start gap-3 rounded-3xl bg-white px-6 py-8" style={CARD_SHADOW}>
            <View className="h-12 w-12 items-center justify-center rounded-2xl bg-brand/10">
                <View className="h-3 w-3 rounded-full bg-brand" />
            </View>
            <Text className="text-xl font-semibold text-ink">{title}</Text>
            <Text className="text-lg text-ink/70">{body}</Text>
        </View>
    );
}
