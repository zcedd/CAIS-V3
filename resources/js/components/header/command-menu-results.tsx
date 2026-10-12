import { FolderKanban, Inbox, Landmark, Package, Users } from 'lucide-react';
import type {
    BeneficiarySearchHit,
    GlobalSearchGroups,
    NamedSearchHit,
    ProgramSearchHit,
    RequestSearchHit,
} from '@/components/header/use-global-search';
import { CommandGroup, CommandItem } from '@/components/ui/command';

type OpenedRecord = {
    label: string;
    url: string;
    group: string;
};

function HighlightMatch({ text, query }: { text: string; query: string }) {
    const needle = query.trim();
    const index = text.toLowerCase().indexOf(needle.toLowerCase());

    if (needle === '' || index === -1) {
        return <>{text}</>;
    }

    return (
        <>
            {text.slice(0, index)}
            <span className="font-semibold text-foreground">
                {text.slice(index, index + needle.length)}
            </span>
            {text.slice(index + needle.length)}
        </>
    );
}

function statusLabel(status: string | null): string | null {
    if (!status) {
        return null;
    }

    return status
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function Meta({ parts }: { parts: Array<string | null | undefined> }) {
    const text = parts
        .filter((part): part is string => Boolean(part))
        .join(' · ');

    if (text === '') {
        return null;
    }

    return (
        <span className="truncate text-xs text-muted-foreground">{text}</span>
    );
}

export function CommandMenuResults({
    groups,
    loading,
    error,
    query,
    fallbacks,
    onOpen,
}: {
    groups: GlobalSearchGroups;
    loading: boolean;
    error: string | null;
    query: string;
    fallbacks: OpenedRecord[];
    onOpen: (record: OpenedRecord) => void;
}) {
    const hits = [
        ...(groups.programs ?? []),
        ...(groups.beneficiaries ?? []),
        ...(groups.requests ?? []),
        ...(groups.items ?? []),
        ...(groups.funds ?? []),
    ];

    if (error) {
        return (
            <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                {error}
            </p>
        );
    }

    if (loading && hits.length === 0) {
        return (
            <div className="space-y-2 p-3">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div
                        key={index}
                        className="h-10 animate-pulse rounded-md bg-muted"
                    />
                ))}
            </div>
        );
    }

    return (
        <>
            <ProgramResults
                programs={groups.programs}
                query={query}
                onOpen={onOpen}
            />
            <BeneficiaryResults
                beneficiaries={groups.beneficiaries}
                query={query}
                onOpen={onOpen}
            />
            <RequestResults
                requests={groups.requests}
                query={query}
                onOpen={onOpen}
            />
            <NamedResults
                heading="Items"
                icon={Package}
                group="items"
                hits={groups.items}
                query={query}
                onOpen={onOpen}
            />
            <NamedResults
                heading="Funds"
                icon={Landmark}
                group="funds"
                hits={groups.funds}
                query={query}
                onOpen={onOpen}
            />
            {!loading && hits.length === 0 && fallbacks.length > 0 ? (
                <CommandGroup heading="Search pages">
                    {fallbacks.map((fallback) => (
                        <CommandItem
                            key={fallback.url}
                            value={fallback.label}
                            onSelect={() => onOpen(fallback)}
                        >
                            {fallback.label}
                        </CommandItem>
                    ))}
                </CommandGroup>
            ) : null}
        </>
    );
}

function ProgramResults({
    programs,
    query,
    onOpen,
}: {
    programs?: ProgramSearchHit[];
    query: string;
    onOpen: (record: OpenedRecord) => void;
}) {
    if (!programs || programs.length === 0) {
        return null;
    }

    return (
        <CommandGroup heading="Programs">
            {programs.map((program) => (
                <CommandItem
                    key={`program-${program.id}`}
                    value={`program ${program.name} ${program.url}`}
                    onSelect={() =>
                        onOpen({
                            label: program.name,
                            url: program.url,
                            group: 'Program',
                        })
                    }
                >
                    <FolderKanban />
                    <span className="flex min-w-0 flex-1 flex-col">
                        <HighlightMatch text={program.name} query={query} />
                        <Meta
                            parts={[
                                program.parent_name,
                                program.is_open ? 'Open' : 'Closed',
                                statusLabel(program.approval_status),
                            ]}
                        />
                    </span>
                </CommandItem>
            ))}
        </CommandGroup>
    );
}

function BeneficiaryResults({
    beneficiaries,
    query,
    onOpen,
}: {
    beneficiaries?: BeneficiarySearchHit[];
    query: string;
    onOpen: (record: OpenedRecord) => void;
}) {
    if (!beneficiaries || beneficiaries.length === 0) {
        return null;
    }

    return (
        <CommandGroup heading="Beneficiaries">
            {beneficiaries.map((beneficiary) => (
                <CommandItem
                    key={`beneficiary-${beneficiary.id}`}
                    value={`beneficiary ${beneficiary.name} ${beneficiary.cais_number ?? ''} ${beneficiary.url}`}
                    onSelect={() =>
                        onOpen({
                            label: beneficiary.name,
                            url: beneficiary.url,
                            group: 'Beneficiary',
                        })
                    }
                >
                    <Users />
                    <span className="flex min-w-0 flex-1 flex-col">
                        <HighlightMatch text={beneficiary.name} query={query} />
                        <Meta
                            parts={[
                                beneficiary.cais_number,
                                beneficiary.type === 'organization'
                                    ? 'Organization'
                                    : 'Individual',
                                beneficiary.barangay,
                                beneficiary.city,
                            ]}
                        />
                    </span>
                </CommandItem>
            ))}
        </CommandGroup>
    );
}

function RequestResults({
    requests,
    query,
    onOpen,
}: {
    requests?: RequestSearchHit[];
    query: string;
    onOpen: (record: OpenedRecord) => void;
}) {
    if (!requests || requests.length === 0) {
        return null;
    }

    return (
        <CommandGroup heading="Requests">
            {requests.map((request) => {
                const title =
                    request.beneficiary_name ?? `Request #${request.id}`;

                return (
                    <CommandItem
                        key={`request-${request.id}`}
                        value={`request ${title} ${request.id} ${request.url}`}
                        onSelect={() =>
                            onOpen({
                                label: title,
                                url: request.url,
                                group: 'Request',
                            })
                        }
                    >
                        <Inbox />
                        <span className="flex min-w-0 flex-1 flex-col">
                            <HighlightMatch text={title} query={query} />
                            <Meta
                                parts={[
                                    `#${request.id}`,
                                    request.program_name,
                                    request.status,
                                    request.date_requested,
                                ]}
                            />
                        </span>
                    </CommandItem>
                );
            })}
        </CommandGroup>
    );
}

function NamedResults({
    heading,
    icon: Icon,
    group,
    hits,
    query,
    onOpen,
}: {
    heading: string;
    icon: typeof Package;
    group: string;
    hits?: NamedSearchHit[];
    query: string;
    onOpen: (record: OpenedRecord) => void;
}) {
    if (!hits || hits.length === 0) {
        return null;
    }

    return (
        <CommandGroup heading={heading}>
            {hits.map((hit) => (
                <CommandItem
                    key={`${group}-${hit.id}`}
                    value={`${group} ${hit.name} ${hit.url}`}
                    onSelect={() =>
                        onOpen({
                            label: hit.name,
                            url: hit.url,
                            group: heading.replace(/s$/, ''),
                        })
                    }
                >
                    <Icon />
                    <HighlightMatch text={hit.name} query={query} />
                </CommandItem>
            ))}
        </CommandGroup>
    );
}
