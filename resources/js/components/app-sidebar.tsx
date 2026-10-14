import { Link } from '@inertiajs/react';
import { Archive, Clapperboard, Library, Megaphone, Tag } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as collection } from '@/routes/collection';
import { index as directors } from '@/routes/directors';
import { index as backRoom } from '@/routes/imports';
import { index as aisles } from '@/routes/movies';
import { index as wantList } from '@/routes/watches';
import type { NavItem } from '@/types';

const storeNavItems: NavItem[] = [
    { title: 'My shelf', href: collection(), icon: Library },
    { title: 'The aisles', href: aisles(), icon: Clapperboard },
    { title: "Directors' row", href: directors(), icon: Megaphone },
    { title: 'Want list', href: wantList(), icon: Tag },
];

const staffNavItems: NavItem[] = [
    { title: 'Back room', href: backRoom(), icon: Archive },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto overflow-visible hover:bg-transparent active:bg-transparent"
                        >
                            <Link href={collection()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="pt-4">
                <NavMain items={storeNavItems} label="Store" />
                <NavMain items={staffNavItems} label="Employees only" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
