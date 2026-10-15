import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Braces, LayoutGrid, Trophy } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Tournaments',
        href: '/tournaments',
        icon: Trophy,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <SidebarGroup className="mt-auto group-data-[collapsible=icon]:p-0">
                    <SidebarGroupLabel className="text-sidebar-foreground/35 text-[10px] font-semibold tracking-[0.16em] uppercase">
                        Resources
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild tooltip="Tournament features" className="h-auto rounded-xl py-2.5">
                                    <Link href="/features">
                                        <BookOpen className="size-4" />
                                        <span>Tournament features</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    tooltip="API documentation"
                                    className="h-auto rounded-xl border border-lime-300/15 bg-lime-300/5 py-2.5 text-lime-200 hover:bg-lime-300/10 hover:text-lime-100"
                                >
                                    <Link href="/api-docs">
                                        <span className="grid size-7 shrink-0 place-items-center rounded-lg bg-lime-300/10">
                                            <Braces className="size-4" />
                                        </span>
                                        <span className="flex min-w-0 flex-col">
                                            <span className="font-medium">API documentation</span>
                                            <span className="text-[10px] font-normal text-white/35">Endpoints and examples</span>
                                        </span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
