import { router, usePage } from '@inertiajs/react';
import {
    CircleHelp,
    Clock,
    FolderKanban,
    Inbox,
    Landmark,
    LogOut,
    Monitor,
    Moon,
    Package,
    PanelLeft,
    Plus,
    Search,
    Settings,
    Stamp,
    Sun,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { toast } from 'sonner';
import { hasTourForPath } from '@/components/app-tour';
import { CommandMenuResults } from '@/components/header/command-menu-results';
import { useCommandRecents } from '@/components/header/use-command-recents';
import { useGlobalSearch } from '@/components/header/use-global-search';
import type { SearchScope } from '@/components/header/use-global-search';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Kbd, KbdGroup } from '@/components/ui/kbd';
import { useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppNavItems } from '@/hooks/use-app-nav-items';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { useCan } from '@/hooks/use-can';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useRegisteredCommands } from '@/lib/command-registry';
import type { PageCommand } from '@/lib/command-registry';
import { Permission } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import {
    index as departmentBeneficiariesIndex,
    create as createBeneficiary,
} from '@/routes/user/beneficiaries';
import { index as departmentFundsIndex } from '@/routes/user/funds';
import { index as departmentItemsIndex } from '@/routes/user/items';
import { index as departmentProgramsIndex } from '@/routes/user/programs';
import { index as departmentQueueIndex } from '@/routes/user/queue';
import type { NavItem } from '@/types';
import type { Auth } from '@/types/auth';

const THEME_OPTIONS: { value: Appearance; label: string; icon: typeof Sun }[] =
    [
        { value: 'light', label: 'Light', icon: Sun },
        { value: 'dark', label: 'Dark', icon: Moon },
        { value: 'system', label: 'System', icon: Monitor },
    ];

const QUICK_ACTION_IDS = [
    'program.create',
    'item.create',
    'fund.create',
    'beneficiary.create',
];

const SCOPE_CHIPS: {
    id: 'all' | 'commands' | 'programs' | 'beneficiaries' | 'requests';
    label: string;
    prefix: '' | '>' | '@' | '#';
}[] = [
    { id: 'all', label: 'All', prefix: '' },
    { id: 'commands', label: 'Commands', prefix: '>' },
    { id: 'programs', label: 'Programs', prefix: '#' },
    { id: 'beneficiaries', label: 'Beneficiaries', prefix: '@' },
    { id: 'requests', label: 'Requests', prefix: '' },
];

type ParsedQuery = {
    mode: 'all' | 'commands' | SearchScope;
    text: string;
    prefix: '' | '>' | '@' | '#';
};

function parseCommandQuery(raw: string): ParsedQuery {
    if (raw.startsWith('>')) {
        return { mode: 'commands', text: raw.slice(1).trim(), prefix: '>' };
    }

    if (raw.startsWith('@')) {
        return {
            mode: 'beneficiaries',
            text: raw.slice(1).trim(),
            prefix: '@',
        };
    }

    if (raw.startsWith('#')) {
        return { mode: 'programs', text: raw.slice(1).trim(), prefix: '#' };
    }

    return { mode: 'all', text: raw.trim(), prefix: '' };
}

function commandMatches(
    query: string,
    label: string,
    keywords?: string,
): boolean {
    const words = query.toLowerCase().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return true;
    }

    const haystack = `${label} ${keywords ?? ''}`.toLowerCase();

    return words.every((word) => haystack.includes(word));
}

function navUrl(href: NavItem['href']): string {
    if (typeof href === 'string') {
        return href;
    }

    if (typeof href === 'object' && href !== null && 'url' in href) {
        return String(href.url);
    }

    return '';
}

function isApplePlatform(): boolean {
    return /Mac|iPhone|iPad/.test(navigator.platform);
}

function subscribeToPlatform(): () => void {
    return () => {};
}

export function HeaderCommandMenu() {
    const { url, props } = usePage<{ auth: Auth }>();
    const user = props.auth.user;
    const slug = user?.department?.slug;
    const [open, setOpen] = useState(false);
    const [raw, setRaw] = useState('');
    const [chip, setChip] = useState<
        'all' | 'commands' | 'programs' | 'beneficiaries' | 'requests'
    >('all');
    const parsed = parseCommandQuery(raw);
    const mode = parsed.prefix === '' ? chip : parsed.mode;
    const showCommands = mode === 'all' || mode === 'commands';
    const searchScope: SearchScope =
        mode === 'commands' || mode === 'all' ? 'all' : mode;
    const { updateAppearance } = useAppearance();
    const can = useCan();
    const cleanup = useMobileNavigation();
    const { toggleSidebar } = useSidebar();
    const registered = useRegisteredCommands();
    const recents = useCommandRecents(user?.id);
    const {
        mainNavItems,
        executiveNavItems,
        administrationNavItems,
        footerNavItems,
    } = useAppNavItems();
    const pages = [
        ...mainNavItems,
        ...executiveNavItems,
        ...administrationNavItems,
        ...footerNavItems,
    ].filter((item) => commandMatches(parsed.text, item.title));
    const pathname = url.split('?')[0] ?? url;
    const showTour = hasTourForPath(pathname);
    const shortcut = useSyncExternalStore(
        subscribeToPlatform,
        () => (isApplePlatform() ? '⌘K' : 'Ctrl K'),
        () => 'Ctrl K',
    );
    const remote = useGlobalSearch(
        slug,
        parsed.text,
        searchScope,
        open && mode !== 'commands',
    );
    const registeredById = new Map(
        registered.map((command) => [command.id, command]),
    );
    const pageCommands = registered.filter(
        (command) =>
            !QUICK_ACTION_IDS.includes(command.id) &&
            commandMatches(parsed.text, command.label, command.keywords),
    );

    function close() {
        setOpen(false);
        setRaw('');
    }

    function visit(target: string) {
        recents.rememberQuery(raw);
        close();
        router.visit(target);
    }

    function openRecord(record: { label: string; url: string; group: string }) {
        recents.rememberQuery(raw);
        recents.rememberOpened(record);
        close();
        router.visit(record.url);
    }

    function runRegisteredOrVisit(id: string, target: string) {
        const command = registeredById.get(id);

        if (command) {
            recents.rememberQuery(raw);
            close();
            command.onSelect();

            return;
        }

        visit(target);
    }

    const quickActions = (
        [
            {
                id: 'program.create',
                label: 'Create program',
                icon: Plus,
                allowed: Boolean(slug) && can(Permission.ProgramCreate),
                run: () =>
                    runRegisteredOrVisit(
                        'program.create',
                        slug
                            ? `${departmentProgramsIndex.url(slug)}?create=1`
                            : '',
                    ),
            },
            {
                id: 'beneficiary.create',
                label: 'Add beneficiary',
                icon: UserPlus,
                allowed: Boolean(slug) && can(Permission.BeneficiaryCreate),
                run: () =>
                    runRegisteredOrVisit(
                        'beneficiary.create',
                        slug ? createBeneficiary.url(slug) : '',
                    ),
            },
            {
                id: 'item.create',
                label: 'Add item',
                icon: Package,
                allowed: Boolean(slug) && can(Permission.ItemCreate),
                run: () =>
                    runRegisteredOrVisit(
                        'item.create',
                        slug
                            ? `${departmentItemsIndex.url(slug)}?create=1`
                            : '',
                    ),
            },
            {
                id: 'fund.create',
                label: 'Add fund',
                icon: Landmark,
                allowed: Boolean(slug) && can(Permission.FundCreate),
                run: () =>
                    runRegisteredOrVisit(
                        'fund.create',
                        slug
                            ? `${departmentFundsIndex.url(slug)}?create=1`
                            : '',
                    ),
            },
        ] satisfies Array<{
            id: string;
            label: string;
            icon: PageCommand['icon'];
            allowed: boolean;
            run: () => void;
        }>
    ).filter(
        (action) => action.allowed && commandMatches(parsed.text, action.label),
    );

    const fallbacks =
        slug && parsed.text.length >= 2
            ? [
                  can(Permission.ProgramViewAny) &&
                  (mode === 'all' || mode === 'programs')
                      ? {
                            label: `Search programs for “${parsed.text}”`,
                            url: departmentProgramsIndex.url(slug, {
                                query: { search: parsed.text },
                            }),
                            group: 'Program',
                        }
                      : null,
                  can(Permission.BeneficiaryViewAny) &&
                  (mode === 'all' || mode === 'beneficiaries')
                      ? {
                            label: `Search beneficiaries for “${parsed.text}”`,
                            url: departmentBeneficiariesIndex.url(slug, {
                                query: { search: parsed.text },
                            }),
                            group: 'Beneficiary',
                        }
                      : null,
                  can(Permission.AssistanceViewAny) &&
                  (mode === 'all' || mode === 'requests')
                      ? {
                            label: `Search requests for “${parsed.text}”`,
                            url: departmentQueueIndex.url(slug, {
                                query: { search: parsed.text },
                            }),
                            group: 'Request',
                        }
                      : null,
                  can(Permission.ItemViewAny) && mode === 'all'
                      ? {
                            label: `Search items for “${parsed.text}”`,
                            url: departmentItemsIndex.url(slug, {
                                query: { search: parsed.text },
                            }),
                            group: 'Item',
                        }
                      : null,
                  can(Permission.FundViewAny) && mode === 'all'
                      ? {
                            label: `Search funds for “${parsed.text}”`,
                            url: departmentFundsIndex.url(slug, {
                                query: { search: parsed.text },
                            }),
                            group: 'Fund',
                        }
                      : null,
              ].filter(
                  (
                      entry,
                  ): entry is { label: string; url: string; group: string } =>
                      entry !== null,
              )
            : [];

    const boxEmpty = parsed.text === '' && mode === 'all';
    const showQueueMine =
        Boolean(slug) &&
        can(Permission.AssistanceViewAny) &&
        commandMatches(parsed.text, 'Queue: My tasks');
    const showQueueTeam =
        Boolean(slug) &&
        can(Permission.AssistanceViewAny) &&
        commandMatches(parsed.text, 'Queue: Team');
    const showAwaitingGovernor =
        Boolean(slug) &&
        can(Permission.ProgramViewAny) &&
        commandMatches(parsed.text, 'Programs awaiting governor');
    const showToggleSidebar = commandMatches(parsed.text, 'Toggle sidebar');
    const showCopyLink = commandMatches(parsed.text, 'Copy page link');
    const showReplayTour =
        showTour && commandMatches(parsed.text, 'Replay page tour');
    const showSettings = commandMatches(parsed.text, 'Settings');
    const showLogout = commandMatches(parsed.text, 'Log out');
    const showShortcuts =
        showQueueMine ||
        showQueueTeam ||
        showAwaitingGovernor ||
        showToggleSidebar ||
        showCopyLink ||
        showReplayTour ||
        showSettings ||
        showLogout;
    const showPreferences = THEME_OPTIONS.some((option) =>
        commandMatches(parsed.text, option.label, 'appearance theme'),
    );

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.key.toLowerCase() === 'k' &&
                (event.metaKey || event.ctrlKey)
            ) {
                event.preventDefault();
                setOpen((current) => !current);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
        };
    }, []);

    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant="outline"
                        className="hidden h-8 w-56 justify-start px-2.5 font-normal text-muted-foreground md:inline-flex"
                        aria-label="Search"
                        data-test="header-command-menu"
                        onClick={() => setOpen(true)}
                    >
                        <Search />
                        <span className="flex-1 text-left">Search...</span>
                        <span className="text-xs">{shortcut}</span>
                        <span className="sr-only">Search</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Search</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="md:hidden"
                        aria-label="Search"
                        onClick={() => setOpen(true)}
                    >
                        <Search />
                        <span className="sr-only">Search</span>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Search</TooltipContent>
            </Tooltip>

            <CommandDialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);

                    if (!next) {
                        setRaw('');
                    }
                }}
                title="Search"
                description="Search records, pages, and actions"
            >
                <Command shouldFilter={false}>
                    <CommandInput
                        value={raw}
                        onValueChange={setRaw}
                        placeholder="Search programs, people, requests..."
                        onKeyDown={(event) => {
                            if (
                                event.key.toLowerCase() === 'k' &&
                                (event.metaKey || event.ctrlKey)
                            ) {
                                event.preventDefault();
                                close();
                            }
                        }}
                    />
                    <div className="flex flex-wrap gap-1.5 px-3 pt-2 pb-2">
                        {SCOPE_CHIPS.map((option) => {
                            const activeId =
                                parsed.prefix === '>'
                                    ? 'commands'
                                    : parsed.prefix === '@'
                                      ? 'beneficiaries'
                                      : parsed.prefix === '#'
                                        ? 'programs'
                                        : chip;

                            return (
                                <button
                                    key={option.id}
                                    type="button"
                                    className={cn(
                                        'rounded-md px-2 py-0.5 text-xs',
                                        activeId === option.id
                                            ? 'bg-primary/10 text-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    )}
                                    onClick={() => {
                                        const text =
                                            parseCommandQuery(raw).text;
                                        setChip(option.id);
                                        setRaw(
                                            option.prefix === ''
                                                ? text
                                                : `${option.prefix}${text === '' ? '' : ` ${text}`}`,
                                        );
                                    }}
                                >
                                    {option.label}
                                </button>
                            );
                        })}
                    </div>
                    <CommandList>
                        {remote.loading || remote.error ? null : (
                            <CommandEmpty>No results found.</CommandEmpty>
                        )}
                        {showCommands &&
                        boxEmpty &&
                        recents.queries.length > 0 ? (
                            <CommandGroup heading="Recent searches">
                                {recents.queries.map((query) => (
                                    <CommandItem
                                        key={query}
                                        value={`recent-query ${query}`}
                                        onSelect={() => setRaw(query)}
                                    >
                                        <Clock />
                                        <span className="min-w-0 flex-1 truncate">
                                            {query}
                                        </span>
                                        <button
                                            type="button"
                                            className="ml-auto rounded-sm text-muted-foreground hover:text-foreground"
                                            aria-label={`Remove search ${query}`}
                                            onPointerDown={(event) => {
                                                event.preventDefault();
                                                event.stopPropagation();
                                            }}
                                            onClick={(event) => {
                                                event.preventDefault();
                                                event.stopPropagation();
                                                recents.forgetQuery(query);
                                            }}
                                        >
                                            <X />
                                        </button>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ) : null}
                        {showCommands &&
                        boxEmpty &&
                        recents.opened.length > 0 ? (
                            <CommandGroup heading="Recently opened">
                                {recents.opened.map((record) => (
                                    <CommandItem
                                        key={record.url}
                                        value={`recent-opened ${record.url}`}
                                        onSelect={() => openRecord(record)}
                                    >
                                        <Clock />
                                        <span className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate">
                                                {record.label}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {record.group}
                                            </span>
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ) : null}
                        {showCommands && pageCommands.length > 0 ? (
                            <CommandGroup heading="On this page">
                                {pageCommands.map((command) => {
                                    const Icon = command.icon;

                                    return (
                                        <CommandItem
                                            key={command.id}
                                            value={`page-command ${command.id}`}
                                            onSelect={() => {
                                                close();
                                                command.onSelect();
                                            }}
                                        >
                                            {Icon ? <Icon /> : null}
                                            {command.label}
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        ) : null}
                        {mode !== 'commands' ? (
                            <CommandMenuResults
                                groups={remote.groups}
                                loading={remote.loading}
                                error={remote.error}
                                query={parsed.text}
                                fallbacks={fallbacks}
                                onOpen={openRecord}
                            />
                        ) : null}
                        {showCommands && quickActions.length > 0 ? (
                            <CommandGroup heading="Quick actions">
                                {quickActions.map((action) => {
                                    const Icon = action.icon;

                                    return (
                                        <CommandItem
                                            key={action.id}
                                            value={`quick ${action.label}`}
                                            onSelect={action.run}
                                        >
                                            {Icon ? <Icon /> : null}
                                            {action.label}
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        ) : null}
                        {showCommands && pages.length > 0 ? (
                            <CommandGroup heading="Pages">
                                {pages.map((item) => {
                                    const Icon = item.icon;
                                    const target = navUrl(item.href);

                                    return (
                                        <CommandItem
                                            key={target || item.title}
                                            value={`page ${item.title} ${target}`}
                                            onSelect={() => visit(target)}
                                        >
                                            {Icon ? <Icon /> : null}
                                            {item.title}
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        ) : null}
                        {showCommands && showShortcuts ? (
                            <CommandGroup heading="Shortcuts">
                                {showQueueMine ? (
                                    <CommandItem
                                        value="queue my tasks"
                                        onSelect={() => {
                                            if (!slug) {
                                                return;
                                            }

                                            visit(
                                                departmentQueueIndex.url(slug, {
                                                    query: {
                                                        tab: 'mine',
                                                    },
                                                }),
                                            );
                                        }}
                                    >
                                        <Inbox />
                                        Queue: My tasks
                                    </CommandItem>
                                ) : null}
                                {showQueueTeam ? (
                                    <CommandItem
                                        value="queue team"
                                        onSelect={() => {
                                            if (!slug) {
                                                return;
                                            }

                                            visit(
                                                departmentQueueIndex.url(slug, {
                                                    query: {
                                                        tab: 'team',
                                                    },
                                                }),
                                            );
                                        }}
                                    >
                                        <Users />
                                        Queue: Team
                                    </CommandItem>
                                ) : null}
                                {showAwaitingGovernor ? (
                                    <CommandItem
                                        value="programs awaiting governor"
                                        onSelect={() => {
                                            if (!slug) {
                                                return;
                                            }

                                            visit(
                                                `${departmentProgramsIndex.url(slug)}?approval[]=awaiting_governor`,
                                            );
                                        }}
                                    >
                                        <Stamp />
                                        Programs awaiting governor
                                    </CommandItem>
                                ) : null}
                                {showToggleSidebar ? (
                                    <CommandItem
                                        value="toggle sidebar"
                                        onSelect={() => {
                                            close();
                                            toggleSidebar();
                                        }}
                                    >
                                        <PanelLeft />
                                        Toggle sidebar
                                    </CommandItem>
                                ) : null}
                                {showCopyLink ? (
                                    <CommandItem
                                        value="copy page link"
                                        onSelect={() => {
                                            void navigator.clipboard
                                                .writeText(window.location.href)
                                                .then(() => {
                                                    toast.success(
                                                        'Page link copied.',
                                                    );
                                                    close();
                                                })
                                                .catch(() => {
                                                    toast.error(
                                                        'Could not copy the page link.',
                                                    );
                                                });
                                        }}
                                    >
                                        <FolderKanban />
                                        Copy page link
                                    </CommandItem>
                                ) : null}
                                {showReplayTour ? (
                                    <CommandItem
                                        value="replay page tour"
                                        onSelect={() => {
                                            close();
                                            window.location.assign(
                                                `${window.location.pathname}?tour=1`,
                                            );
                                        }}
                                    >
                                        <CircleHelp />
                                        Replay page tour
                                    </CommandItem>
                                ) : null}
                                {showSettings ? (
                                    <CommandItem
                                        value="settings"
                                        onSelect={() =>
                                            visit(editProfile.url())
                                        }
                                    >
                                        <Settings />
                                        Settings
                                    </CommandItem>
                                ) : null}
                                {showLogout ? (
                                    <CommandItem
                                        value="log out"
                                        onSelect={() => {
                                            cleanup();
                                            router.flushAll();
                                            close();
                                            router.post(logout.url());
                                        }}
                                    >
                                        <LogOut />
                                        Log out
                                    </CommandItem>
                                ) : null}
                            </CommandGroup>
                        ) : null}
                        {showCommands && showPreferences ? (
                            <CommandGroup heading="Preferences">
                                {THEME_OPTIONS.filter((option) =>
                                    commandMatches(
                                        parsed.text,
                                        option.label,
                                        'appearance theme',
                                    ),
                                ).map((option) => {
                                    const Icon = option.icon;

                                    return (
                                        <CommandItem
                                            key={option.value}
                                            value={`appearance ${option.label}`}
                                            onSelect={() => {
                                                updateAppearance(option.value);
                                                close();
                                            }}
                                        >
                                            <Icon />
                                            {option.label}
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        ) : null}
                    </CommandList>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 border-t px-3 py-2 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                            <KbdGroup>
                                <Kbd>↑</Kbd>
                                <Kbd>↓</Kbd>
                            </KbdGroup>
                            Navigate
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Kbd>Enter</Kbd>
                            Open
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Kbd>Esc</Kbd>
                            Close
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Kbd>&gt;</Kbd>
                            Commands
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Kbd>@</Kbd>
                            Beneficiaries
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Kbd>#</Kbd>
                            Programs
                        </span>
                    </div>
                </Command>
            </CommandDialog>
        </>
    );
}
