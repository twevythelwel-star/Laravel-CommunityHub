

'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useMemo, useState } from 'react';

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
  Moon,
  PiggyBank,
  QrCode,
  Search,
  Settings,
  ShieldOff,
  Siren,
  Sun,
  User,
  UserCog,
  UserX,
  Users,
  BadgeCheck,
  ListTree,
} from 'lucide-react';
import { useDarkMode } from '@/hooks/use-dark-mode';
import { Logo } from '@/components/logo';
import { UserNav } from '@/components/user-nav';
import { Input } from '@/components/ui/input';
import { useAuth } from '@/context/auth-context';
import type { UserRole } from '@/types';
import { useIsClient } from '@/hooks/use-is-client';
import { Button } from '@/components/ui/button';
import { MyGatePassDialog } from '@/components/dashboard/my-gate-pass-dialog';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Plus } from "lucide-react";

const allMenuItems = [
  { href: '/dashboard', label: 'Dashboard', icon: Home, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/map', label: 'Community Map', icon: Map, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/deals', label: 'Perks & Savings', icon: Gift, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/fundraising', label: 'Fundraising', icon: PiggyBank, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/guidelines', label: 'Guidelines', icon: BookUser, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/directory', label: 'Directory', icon: Users, roles: ['System Admin', 'Admin'] },
  { href: '/dashboard/renters', label: 'My Renters', icon: KeyRound, roles: ['Homeowner'] },
  { href: '/dashboard/visitors', label: 'Visitors', icon: User, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/gate-pass', label: 'Gate Pass', icon: BadgeCheck, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'] },
  { href: '/dashboard/calendar', label: 'Calendar', icon: Calendar, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/notifications', label: 'Notifications', icon: Bell, roles: ['System Admin', 'Admin', 'Homeowner'] },
  { href: '/dashboard/updates', label: 'Updates', icon: ClipboardList, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/warnings', label: 'Safety Alert', icon: Siren, roles: ['System Admin', 'Admin', 'Homeowner', 'Security'] },
  { href: '/dashboard/block-list', label: 'Block List', icon: ShieldOff, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/access-log', label: 'Access Log', icon: ListTree, roles: ['System Admin', 'Admin'] },
  { href: '/dashboard/billing', label: 'Billing', icon: CreditCard, roles: ['System Admin', 'Admin', 'Homeowner'] },
  { href: '/dashboard/changelog', label: 'App Changelog', icon: History, roles: ['System Admin'] },
  { href: '/dashboard/review-feedback', label: 'Review Feedback', icon: ClipboardCheck, roles: ['System Admin'] },
  { href: '/dashboard/feedback', label: 'Submit Feedback', icon: MessageSquarePlus, roles: ['Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/deactivation', label: 'Deactivation', icon: UserX, roles: ['Homeowner'] },
  { href: '/dashboard/settings', label: 'Settings', icon: Settings, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/profile', label: 'Profile', icon: User, roles: ['Staff', 'Security'] },
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
  const [isPassOpen, setPassOpen] = useState(false);
  const { isDark, toggle: toggleDark } = useDarkMode();

  useEffect(() => {
    if (isClient && !loading && !user) {
      router.push('/');
    }
  }, [user, loading, router, isClient]);
  
  const menuItems = useMemo(() => {
    if (!user) return [];
    
    // Always show profile link for Staff
    if (user.role === 'Staff') {
        return allMenuItems.filter(item => item.href === '/dashboard/profile' || item.href === '/dashboard/gate-pass');
    }

    const filteredItems = allMenuItems.filter(item => item.roles.includes(user.role));

    if (user.role === 'Security') {
      // Show profile link explicitly for security
      const securityItems = allMenuItems.filter(item => item.roles.includes(user.role));
      const profileItem = allMenuItems.find(item => item.href === '/dashboard/profile');
      if (profileItem && !securityItems.some(i => i.href === '/dashboard/profile')) {
        return [...securityItems, profileItem];
      }
      return securityItems;
    }

    return filteredItems;

  }, [user]);

  if (!isClient || loading || !user) {
    return <div className="flex h-screen w-full items-center justify-center">Loading...</div>;
  }

  return (
    <SidebarProvider>
      <MyGatePassDialog open={isPassOpen} onOpenChange={setPassOpen} />
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
              className="w-full max-w-xs md:max-w-none rounded-lg bg-background pl-8 md:w-[200px] lg:w-[320px]"
            />
          </div>
          <div className="hidden items-center gap-2 sm:flex">
             <Tooltip>
                 <TooltipTrigger asChild>
                     <Button variant="ghost" size="icon" onClick={() => setPassOpen(true)} aria-label="View Gate Pass">
                        <QrCode className="h-5 w-5" />
                        <span className="sr-only">View Gate Pass</span>
                    </Button>
                 </TooltipTrigger>
                 <TooltipContent>View Gate Pass</TooltipContent>
             </Tooltip>
             <Tooltip>
                 <TooltipTrigger asChild>
                     <Button variant="ghost" size="icon" onClick={toggleDark} aria-label="Toggle dark mode">
                        {isDark ? <Sun className="h-5 w-5" /> : <Moon className="h-5 w-5" />}
                    </Button>
                 </TooltipTrigger>
                 <TooltipContent>{isDark ? 'Light Mode' : 'Dark Mode'}</TooltipContent>
             </Tooltip>
             
             <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="default" size="sm" className="hidden md:flex gap-1 h-9 px-4 rounded-full shadow-md hover:shadow-lg transition-all duration-200 bg-primary text-primary-foreground">
                    <Plus className="h-4 w-4" />
                    <span>Quick Action</span>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                  <DropdownMenuLabel>Quick Actions</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem asChild>
                    <Link href="/dashboard/directory?action=add" className="cursor-pointer">
                      <Users className="mr-2 h-4 w-4" />
                      <span>Add Resident</span>
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link href="/dashboard/calendar?action=create" className="cursor-pointer">
                      <Calendar className="mr-2 h-4 w-4" />
                      <span>Create Event</span>
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link href="/dashboard/updates?action=new" className="cursor-pointer">
                      <Bell className="mr-2 h-4 w-4" />
                      <span>New Announcement</span>
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link href="/dashboard/visitors?action=register" className="cursor-pointer">
                      <User className="mr-2 h-4 w-4" />
                      <span>Register Visitor</span>
                    </Link>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>

              <Button variant="default" size="icon" className="md:hidden rounded-full h-10 w-10 bg-primary text-primary-foreground shadow-md">
                <Plus className="h-5 w-5" />
              </Button>
            <UserNav />
          </div>
        </header>
        <main key={pathname} className="flex flex-1 flex-col gap-4 p-4 sm:px-6 sm:py-0 md:gap-8 animate-fade-in">
          {children}
        </main>
      </SidebarInset>
    </SidebarProvider>
  );
}
