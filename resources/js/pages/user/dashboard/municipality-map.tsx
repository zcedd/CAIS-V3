import { geoArea, geoMercator, geoPath } from 'd3-geo';
import type { FeatureCollection, Geometry } from 'geojson';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { formatPeso } from '@/lib/format-peso';
import { cn } from '@/lib/utils';
import { MunicipalityMapSkeleton } from '@/pages/user/dashboard/municipality-map-skeleton';
import type { MunicipalityMapPoint } from '@/types/dashboard';

const MAP_WIDTH = 480;
const MAP_HEIGHT = 640;
const MAP_URL = '/geo/ilocos-norte-municipalities.json';

type MunicipalityCollection = FeatureCollection<Geometry, { name: string }>;

type MapShape = {
    key: string;
    label: string;
    d: string;
    x: number;
    y: number;
    count: number;
    row: MunicipalityMapPoint | null;
};

type TooltipState = {
    x: number;
    y: number;
    flip: boolean;
};

let municipalitiesRequest: Promise<MunicipalityCollection> | null = null;

function loadMunicipalities(): Promise<MunicipalityCollection> {
    municipalitiesRequest ??= fetch(MAP_URL)
        .then((response) => {
            if (!response.ok) {
                throw new Error('Could not load the municipality map.');
            }

            return response.json() as Promise<MunicipalityCollection>;
        })
        .then((collection) => rewindForD3(collection))
        .catch((error: unknown) => {
            municipalitiesRequest = null;

            throw error;
        });

    return municipalitiesRequest;
}

/**
 * d3-geo expects clockwise outer rings. Counter-clockwise rings (the GeoJSON
 * standard) are read as the rest of the globe, so the province collapses to a dot.
 */
function rewindForD3(
    collection: MunicipalityCollection,
): MunicipalityCollection {
    return {
        ...collection,
        features: collection.features.map((feature) => {
            if (geoArea(feature) <= 2 * Math.PI) {
                return feature;
            }

            const geometry = feature.geometry;

            if (geometry.type === 'Polygon') {
                return {
                    ...feature,
                    geometry: {
                        ...geometry,
                        coordinates: geometry.coordinates.map((ring) =>
                            [...ring].reverse(),
                        ),
                    },
                };
            }

            if (geometry.type === 'MultiPolygon') {
                return {
                    ...feature,
                    geometry: {
                        ...geometry,
                        coordinates: geometry.coordinates.map((polygon) =>
                            polygon.map((ring) => [...ring].reverse()),
                        ),
                    },
                };
            }

            return feature;
        }),
    };
}

export function normalizeMunicipalityName(name: string): string {
    return name
        .toLowerCase()
        .replace(/\([^)]*\)/g, ' ')
        .replace(/\bcity of\b/g, ' ')
        .replace(/\bcity\b/g, ' ')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

export function MunicipalityMap({
    municipalities,
    details = 'full',
    compact = false,
}: {
    municipalities: MunicipalityMapPoint[];
    details?: 'full' | 'people';
    compact?: boolean;
}) {
    const frameRef = useRef<HTMLDivElement>(null);
    const [collection, setCollection] = useState<MunicipalityCollection | null>(
        null,
    );
    const [failed, setFailed] = useState(false);
    const [activeKey, setActiveKey] = useState<string | null>(null);
    const [tooltip, setTooltip] = useState<TooltipState | null>(null);

    useEffect(() => {
        let active = true;

        loadMunicipalities()
            .then((data) => {
                if (active) {
                    setCollection(data);
                }
            })
            .catch(() => {
                if (active) {
                    setFailed(true);
                }
            });

        return () => {
            active = false;
        };
    }, []);

    const countsByName = useMemo(() => {
        const counts = new Map<string, MunicipalityMapPoint>();

        for (const row of municipalities) {
            counts.set(normalizeMunicipalityName(row.city), row);
        }

        return counts;
    }, [municipalities]);

    const maxBeneficiaries = useMemo(
        () =>
            municipalities.reduce(
                (max, row) => Math.max(max, row.beneficiaries),
                0,
            ),
        [municipalities],
    );

    const shapes = useMemo(() => {
        if (!collection) {
            return [];
        }

        const projection = geoMercator().fitSize(
            [MAP_WIDTH, MAP_HEIGHT],
            collection,
        );
        const path = geoPath(projection);

        return collection.features.flatMap((feature) => {
            const outline = path(feature);

            if (!outline) {
                return [];
            }

            const [x, y] = path.centroid(feature);
            const featureName = feature.properties.name;
            const key = normalizeMunicipalityName(featureName);
            const row = countsByName.get(key) ?? null;

            return [
                {
                    key,
                    label: row?.city ?? featureName,
                    d: outline,
                    x,
                    y,
                    count: row?.beneficiaries ?? 0,
                    row,
                } satisfies MapShape,
            ];
        });
    }, [collection, countsByName]);

    const activeShape = shapes.find((shape) => shape.key === activeKey) ?? null;

    function placeTooltip(clientX: number, clientY: number): void {
        const bounds = frameRef.current?.getBoundingClientRect();

        if (!bounds) {
            return;
        }

        const x = clientX - bounds.left;
        const y = clientY - bounds.top;

        setTooltip({ x, y, flip: x > bounds.width / 2 });
    }

    function placeTooltipAtShape(shape: MapShape): void {
        const svg = frameRef.current?.querySelector('svg');

        if (!svg) {
            setTooltip(null);

            return;
        }

        const svgBounds = svg.getBoundingClientRect();

        placeTooltip(
            svgBounds.left + (shape.x / MAP_WIDTH) * svgBounds.width,
            svgBounds.top + (shape.y / MAP_HEIGHT) * svgBounds.height,
        );
    }

    return (
        <section
            className={cn(
                'flex flex-col',
                compact
                    ? 'w-full'
                    : 'h-full rounded-xl border border-border bg-card p-4',
            )}
        >
            <h2 className="text-sm font-medium">Ilocos Norte</h2>
            <p className="mb-3 text-xs text-muted-foreground">
                {details === 'people'
                    ? 'Hover a municipality to see how many people are recorded there.'
                    : 'Hover a municipality to see beneficiaries who received program items.'}
            </p>
            <div
                ref={frameRef}
                className={cn(
                    'relative flex flex-col',
                    compact ? undefined : 'min-h-64 flex-1',
                )}
                onMouseLeave={() => {
                    setActiveKey(null);
                    setTooltip(null);
                }}
            >
                <div
                    className={cn(
                        'relative',
                        compact ? 'aspect-3/4' : 'min-h-64 flex-1',
                    )}
                >
                    {failed ? (
                        <p className="flex h-full min-h-64 items-center justify-center text-sm text-muted-foreground">
                            The municipality map could not be loaded.
                        </p>
                    ) : shapes.length === 0 ? (
                        <Skeleton className="absolute inset-0 h-full w-full" />
                    ) : (
                        <svg
                            viewBox={`0 0 ${MAP_WIDTH} ${MAP_HEIGHT}`}
                            className="absolute inset-0 h-full w-full"
                            role="group"
                            aria-label="Map of Ilocos Norte municipalities"
                        >
                            {shapes.map((shape) => {
                                const isActive = shape.key === activeKey;

                                return (
                                    <path
                                        key={shape.key}
                                        d={shape.d}
                                        tabIndex={0}
                                        role="button"
                                        aria-label={shapeLabel(shape, details)}
                                        className="cursor-pointer outline-none"
                                        fill={
                                            shape.count > 0
                                                ? 'var(--chart-1)'
                                                : 'var(--muted)'
                                        }
                                        fillOpacity={fillOpacity(
                                            shape.count,
                                            maxBeneficiaries,
                                            isActive,
                                        )}
                                        stroke={
                                            isActive
                                                ? 'var(--foreground)'
                                                : 'var(--background)'
                                        }
                                        strokeWidth={isActive ? 2.4 : 0.8}
                                        onMouseEnter={(event) => {
                                            setActiveKey(shape.key);
                                            placeTooltip(
                                                event.clientX,
                                                event.clientY,
                                            );
                                        }}
                                        onMouseMove={(event) => {
                                            setActiveKey(shape.key);
                                            placeTooltip(
                                                event.clientX,
                                                event.clientY,
                                            );
                                        }}
                                        onMouseLeave={() => {
                                            setActiveKey(null);
                                            setTooltip(null);
                                        }}
                                        onFocus={() => {
                                            setActiveKey(shape.key);
                                            placeTooltipAtShape(shape);
                                        }}
                                        onBlur={() => {
                                            setActiveKey(null);
                                            setTooltip(null);
                                        }}
                                    />
                                );
                            })}
                            {activeShape ? (
                                <path
                                    d={activeShape.d}
                                    pointerEvents="none"
                                    fill={
                                        activeShape.count > 0
                                            ? 'var(--chart-1)'
                                            : 'var(--muted)'
                                    }
                                    fillOpacity={1}
                                    stroke="var(--foreground)"
                                    strokeWidth={2.4}
                                />
                            ) : null}
                        </svg>
                    )}
                </div>
                <div className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                    <span>None</span>
                    <span
                        className="h-2 w-24 rounded-full border border-border"
                        style={{
                            background:
                                'linear-gradient(to right, var(--muted), var(--chart-1))',
                        }}
                    />
                    <span>
                        {details === 'people'
                            ? 'Most people'
                            : 'Most beneficiaries'}
                    </span>
                </div>

                {activeShape && tooltip ? (
                    <MunicipalityTooltip
                        row={
                            activeShape.row ?? {
                                city_id: 0,
                                city: activeShape.label,
                                beneficiaries: 0,
                                delivered_requests: 0,
                                goods_released: 0,
                                cash_disbursed: 0,
                            }
                        }
                        tooltip={tooltip}
                        details={details}
                    />
                ) : null}
            </div>
        </section>
    );
}

function MunicipalityTooltip({
    row,
    tooltip,
    details,
}: {
    row: MunicipalityMapPoint;
    tooltip: TooltipState;
    details: 'full' | 'people';
}) {
    return (
        <div
            role="tooltip"
            className="pointer-events-none absolute z-10 w-52 rounded-lg border border-border bg-popover px-3 py-2 text-popover-foreground shadow-md"
            style={{
                left: tooltip.x,
                top: tooltip.y,
                transform: tooltip.flip
                    ? 'translate(calc(-100% - 12px), 12px)'
                    : 'translate(12px, 12px)',
            }}
        >
            <p className="text-sm font-medium">{row.city}</p>
            <dl className="mt-1 space-y-0.5 text-xs">
                {details === 'people' ? (
                    <TooltipRow
                        label="People"
                        value={row.beneficiaries.toLocaleString()}
                    />
                ) : (
                    <>
                        <TooltipRow
                            label="Beneficiaries served"
                            value={row.beneficiaries.toLocaleString()}
                        />
                        <TooltipRow
                            label="Delivered requests"
                            value={row.delivered_requests.toLocaleString()}
                        />
                        <TooltipRow
                            label="Goods released"
                            value={row.goods_released.toLocaleString()}
                        />
                        <TooltipRow
                            label="Cash disbursed"
                            value={formatPeso(row.cash_disbursed)}
                        />
                    </>
                )}
            </dl>
        </div>
    );
}

function TooltipRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

function shapeLabel(shape: MapShape, details: 'full' | 'people'): string {
    if (details === 'people') {
        const people =
            shape.count === 1
                ? '1 person'
                : `${shape.count.toLocaleString()} people`;

        return `${shape.label}, ${people}`;
    }

    const served =
        shape.count === 1
            ? '1 beneficiary served'
            : `${shape.count.toLocaleString()} beneficiaries served`;

    return `${shape.label}, ${served}`;
}

function fillOpacity(count: number, max: number, active: boolean): number {
    if (count <= 0) {
        return 1;
    }

    if (active || max <= 0) {
        return 1;
    }

    return 0.28 + 0.72 * (count / max);
}

export { MunicipalityMapSkeleton };
