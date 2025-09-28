

'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useMemo } from 'react';

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuButton,
  SidebarProvider,
  SidebarTrigger,
} from '@/components/ui/sidebar';
import {
  Bell,
  BookUser,
  Calendar,
  ClipboardCheck,
  ClipboardList,
  CreditCard,
  Gift,
  History,
  Home,
  KeyRound,
  Map,
  MessageSquarePlus,
  PiggyBank,
  Search,
  Settings,
  ShieldOff,
  Siren,
  User,
  UserCog,
  UserX,
  Users,
  BadgeCheck,
} from 'lucide-react';
import { Logo } from '@/components/logo';
import { UserNav } from '@/components/user-nav';
import { Input } from '@/components/ui/input';
import { useAuth } from '@/context/auth-context';
import type { UserRole } from '@/types';
import { useIsClient } from '@/hooks/use-is-client';

const allMenuItems = [
  { href: '/dashboard', label: 'Dashboard', icon: Home, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/map', label: 'Community Map', icon: Map, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/deals', label: 'Deals', icon: Gift, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/fundraising', label: 'Fundraising', icon: PiggyBank, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/guidelines', label: 'Guidelines', icon: BookUser, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/directory', label: 'Directory', icon: Users, roles: ['System Admin', 'Admin'] },
  { href: '/dashboard/renters', label: 'My Renters', icon: KeyRound, roles: ['Homeowner'] },
  { href: '/dashboard/visitors', label: 'Visitors', icon: User, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/gate-pass', label: 'Gate Pass', icon: BadgeCheck, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/calendar', label: 'Calendar', icon: Calendar, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/notifications', label: 'Notifications', icon: Bell, roles: ['System Admin', 'Admin', 'Homeowner'] },
  { href: '/dashboard/updates', label: 'Updates', icon: ClipboardList, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/warnings', label: 'Warnings', icon: Siren, roles: ['System Admin', 'Admin', 'Homeowner', 'Security'] },
  { href: '/dashboard/block-list', label: 'Block List', icon: ShieldOff, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/billing', label: 'Billing', icon: CreditCard, roles: ['System Admin', 'Admin', 'Homeowner'] },
  { href: '/dashboard/users', label: 'User Management', icon: UserCog, roles: ['System Admin', 'Admin'] },
  { href: '/dashboard/changelog', label: 'App Changelog', icon: History, roles: ['System Admin'] },
  { href: '/dashboard/review-feedback', label: 'Review Feedback', icon: ClipboardCheck, roles: ['System Admin'] },
  { href: '/dashboard/feedback', label: 'Submit Feedback', icon: MessageSquarePlus, roles: ['Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/deactivation', label: 'Deactivation', icon: UserX, roles: ['Homeowner'] },
  { href: '/dashboard/settings', label: 'Settings', icon: Settings, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
];

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const { user, loading } = useAuth();
  const router = useRouter();
  const isClient = useIsClient();

  useEffect(() => {
    if (isClient && !loading && !user) {
      router.push('/');
    }
  }, [user, loading, router, isClient]);
  
  const menuItems = useMemo(() => {
    if (!user) return [];
    // A regular admin should not see the full directory, but the user management page
    if (user.role === 'Admin') {
      return allMenuItems.filter(item => item.href !== '/dashboard/directory' && item.roles.includes(user.role));
    }
    const filteredItems = allMenuItems.filter(item => item.roles.includes(user.role));

    if (user.role === 'Security') {
      return filteredItems.filter(item => item.href !== '/dashboard/updates');
    }

    return filteredItems;

  }, [user]);

  if (!isClient || loading || !user) {
    return <div className="flex h-screen w-full items-center justify-center">Loading...</div>;
  }

  return (
    <SidebarProvider>
      <Sidebar>
        <SidebarHeader>
          <Logo />
        </SidebarHeader>
        <SidebarContent>
          <SidebarMenu>
            {menuItems.map(({ href, label, icon: Icon }) => (
              <SidebarMenuItem key={href}>
                 <Link href={href} className="w-full">
                  <SidebarMenuButton
                    isActive={pathname === href}
                    tooltip={label}
                  >
                    <Icon />
                    <span>{label}</span>
                  </SidebarMenuButton>
                </Link>
              </SidebarMenuItem>
            ))}
          </SidebarMenu>
        </SidebarContent>
        <SidebarFooter>
          <UserNav />
        </SidebarFooter>
      </Sidebar>
      <SidebarInset>
        <header className="sticky top-0 z-30 flex h-14 items-center gap-4 border-b bg-background/80 px-4 backdrop-blur-sm sm:static sm:h-auto sm:border-0 sm:bg-transparent sm:px-6">
          <SidebarTrigger className="sm:hidden" />
          <div className="relative ml-auto flex-1 md:grow-0">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              type="search"
              placeholder="Search..."
              className="w-full rounded-lg bg-background pl-8 md:w-[200px] lg:w-[320px]"
            />
          </div>
          <div className="hidden sm:block">
            <UserNav />
          </div>
        </header>
        <main className="flex flex-1 flex-col gap-4 p-4 sm:px-6 sm:py-0 md:gap-8">
          {children}
        </main>
      </SidebarInset>
    </SidebarProvider>
  );
}
