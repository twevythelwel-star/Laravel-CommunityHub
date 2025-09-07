'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useMemo } from 'react';

import { cn } from '@/lib/utils';
import {
  SidebarProvider,
  Sidebar,
  SidebarHeader,
  SidebarContent,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuButton,
  SidebarFooter,
  SidebarTrigger,
  SidebarInset,
} from '@/components/ui/sidebar';
import {
  Bell,
  Calendar,
  Home,
  Siren,
  User,
  Users,
  ShieldOff,
  Search,
  Settings,
  UserCog,
  Camera,
} from 'lucide-react';
import { Logo } from '@/components/logo';
import { UserNav } from '@/components/user-nav';
import { Input } from '@/components/ui/input';
import { useAuth } from '@/context/auth-context';
import type { UserRole } from '@/types';

const allMenuItems = [
  { href: '/dashboard', label: 'Dashboard', icon: Home, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/directory', label: 'Directory', icon: Users, roles: ['System Admin', 'Admin', 'Homeowner'] },
  { href: '/dashboard/visitors', label: 'Visitors', icon: User, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/calendar', label: 'Calendar', icon: Calendar, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/notifications', label: 'Notifications', icon: Bell, roles: ['System Admin', 'Admin', 'Homeowner'] },
  { href: '/dashboard/warnings', label: 'Warnings', icon: Siren, roles: ['System Admin', 'Admin', 'Homeowner', 'Security'] },
  { href: '/dashboard/block-list', label: 'Block List', icon: ShieldOff, roles: ['System Admin', 'Admin', 'Security'] },
  { href: '/dashboard/users', label: 'User Management', icon: UserCog, roles: ['System Admin', 'Admin'] },
];

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) {
      router.push('/');
    }
  }, [user, loading, router]);
  
  const menuItems = useMemo(() => {
    if (!user) return [];
    return allMenuItems.filter(item => item.roles.includes(user.role));
  }, [user]);

  if (loading || !user) {
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
            {menuItems.map(({ href, label, icon: Icon, isBeta }) => (
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
