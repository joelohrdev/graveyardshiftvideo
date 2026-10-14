import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { TrackingGlitch } from '@/components/store/tracking-glitch';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <TrackingGlitch />
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="min-w-0 overflow-x-clip md:border md:border-border/40"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="flex-1 pb-12">{children}</div>
            </AppContent>
        </AppShell>
    );
}
