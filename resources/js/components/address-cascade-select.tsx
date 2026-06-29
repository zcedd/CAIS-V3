'use client';

import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    AddressBarangayOption,
    AddressCityOption,
    AddressProvinceOption,
} from '@/types/beneficiary';
import { useEffect, useMemo, useState } from 'react';

type AddressCascadeSelectProps = {
    provinces: AddressProvinceOption[];
    defaultProvinceId: number | null;
    cities: AddressCityOption[];
    barangays: AddressBarangayOption[];
    value: number | null;
    onChange: (barangayId: number | null) => void;
    name: string;
    error?: string;
    idPrefix?: string;
};

function resolveCityId(
    barangayId: number | null,
    barangays: AddressBarangayOption[],
): number | null {
    if (barangayId === null) {
        return null;
    }

    return (
        barangays.find((barangay) => barangay.id === barangayId)
            ?.address_city_id ?? null
    );
}

function resolveProvinceId(
    barangayId: number | null,
    barangays: AddressBarangayOption[],
    cities: AddressCityOption[],
): number | null {
    const cityId = resolveCityId(barangayId, barangays);

    if (cityId === null) {
        return null;
    }

    return (
        cities.find((city) => city.id === cityId)?.address_province_id ?? null
    );
}

function resolveDefaultProvinceId(
    provinces: AddressProvinceOption[],
    defaultProvinceId: number | null,
    barangayId: number | null,
    barangays: AddressBarangayOption[],
    cities: AddressCityOption[],
): number | null {
    const fromBarangay = resolveProvinceId(barangayId, barangays, cities);

    if (fromBarangay !== null) {
        return fromBarangay;
    }

    if (
        defaultProvinceId !== null &&
        provinces.some((province) => province.id === defaultProvinceId)
    ) {
        return defaultProvinceId;
    }

    if (provinces.length === 1) {
        return provinces[0].id;
    }

    return null;
}

export function AddressCascadeSelect({
    provinces,
    defaultProvinceId,
    cities,
    barangays,
    value,
    onChange,
    name,
    error,
    idPrefix = 'address',
}: AddressCascadeSelectProps) {
    const [provinceId, setProvinceId] = useState<number | null>(() =>
        resolveDefaultProvinceId(
            provinces,
            defaultProvinceId,
            value,
            barangays,
            cities,
        ),
    );
    const [cityId, setCityId] = useState<number | null>(() =>
        resolveCityId(value, barangays),
    );

    useEffect(() => {
        if (value === null) {
            return;
        }

        setProvinceId(resolveProvinceId(value, barangays, cities));
        setCityId(resolveCityId(value, barangays));
    }, [value, barangays, cities]);

    const citiesForProvince = useMemo(
        () =>
            provinceId === null
                ? []
                : cities.filter(
                      (city) => city.address_province_id === provinceId,
                  ),
        [cities, provinceId],
    );

    const barangaysForCity = useMemo(
        () =>
            cityId === null
                ? []
                : barangays.filter(
                      (barangay) => barangay.address_city_id === cityId,
                  ),
        [barangays, cityId],
    );

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}_province`}>Province</Label>
                <Select
                    value={provinceId !== null ? String(provinceId) : undefined}
                    onValueChange={(nextValue) => {
                        setProvinceId(Number(nextValue));
                        setCityId(null);
                        onChange(null);
                    }}
                    disabled={provinces.length === 0}
                >
                    <SelectTrigger
                        id={`${idPrefix}_province`}
                        className="w-full"
                    >
                        <SelectValue
                            placeholder={
                                provinces.length === 0
                                    ? 'No provinces available'
                                    : 'Select province'
                            }
                        />
                    </SelectTrigger>
                    <SelectContent position="popper">
                        {provinces.map((province) => (
                            <SelectItem
                                key={province.id}
                                value={String(province.id)}
                            >
                                {province.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}_city`}>City / Municipality</Label>
                <Select
                    value={cityId !== null ? String(cityId) : undefined}
                    onValueChange={(nextValue) => {
                        setCityId(Number(nextValue));
                        onChange(null);
                    }}
                    disabled={
                        provinceId === null || citiesForProvince.length === 0
                    }
                >
                    <SelectTrigger id={`${idPrefix}_city`} className="w-full">
                        <SelectValue
                            placeholder={
                                provinceId === null
                                    ? 'Select a province first'
                                    : citiesForProvince.length === 0
                                      ? 'No cities available'
                                      : 'Select city or municipality'
                            }
                        />
                    </SelectTrigger>
                    <SelectContent position="popper">
                        {citiesForProvince.map((city) => (
                            <SelectItem key={city.id} value={String(city.id)}>
                                {city.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}_barangay`}>Barangay</Label>
                <Select
                    value={value !== null ? String(value) : undefined}
                    onValueChange={(nextValue) => onChange(Number(nextValue))}
                    disabled={cityId === null}
                >
                    <SelectTrigger
                        id={`${idPrefix}_barangay`}
                        className="w-full"
                        aria-invalid={error ? true : undefined}
                    >
                        <SelectValue
                            placeholder={
                                cityId === null
                                    ? 'Select a city or municipality first'
                                    : 'Select barangay'
                            }
                        />
                    </SelectTrigger>
                    <SelectContent position="popper">
                        {barangaysForCity.map((barangay) => (
                            <SelectItem
                                key={barangay.id}
                                value={String(barangay.id)}
                            >
                                {barangay.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={error} />
                <input type="hidden" name={name} value={value ?? ''} />
            </div>
        </div>
    );
}
