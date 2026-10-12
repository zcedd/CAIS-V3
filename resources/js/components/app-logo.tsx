import AppLogoIcon from '@/components/app-logo-icon';

type AppLogoProps = {
    name?: string;
    wrap?: boolean;
};

export default function AppLogo({ name = 'CAIS', wrap = false }: AppLogoProps) {
    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-transparent">
                <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
            </div>
            <div className="ml-1 grid min-w-0 flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
                <span
                    className={
                        wrap
                            ? 'mb-0.5 text-xs leading-tight font-semibold whitespace-normal'
                            : 'mb-0.5 truncate leading-tight font-semibold'
                    }
                >
                    {name}
                </span>
            </div>
        </>
    );
}
