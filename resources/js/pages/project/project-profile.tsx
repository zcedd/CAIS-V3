import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { index, show } from '@/routes/project';
import { type BreadcrumbItem } from '@/types';
import { Project } from '@/types/model';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Project List',
        href: index().url,
    },
    {
        title: 'Profile',
        href: show(1).url,
    },
];

export default function ProjectProfile({ Project }: { Project: Project[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Project" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* <Button>Create Project</Button>
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {Projects &&
                        Projects.map((project, idx) => (
                            <div
                                key={project.id ?? idx}
                                className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                                <div className="absolute right-0 bottom-0 left-0 flex flex-col bg-white/80 p-2 dark:bg-black/60">
                                    <span className="font-semibold">{project.name}</span>
                                    <span className="">{project.is_organization ? 'Organization' : 'Personal'}</span>
                                </div>
                            </div>
                        ))}
                </div> */}
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </AppLayout>
    );
}
