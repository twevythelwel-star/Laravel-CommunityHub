

import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { WelcomeAnimation } from '@/components/welcome-animation';

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
  ShieldCheck,
  Scan,
  LogOut,
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Toaster } from '@/components/ui/toaster';
import { FlashToaster } from '@/components/flash-toaster';
import { BrandingProvider } from '@/context/branding-context';
import { ThemeProvider } from '@/context/theme-context';
import { EmergencyBroadcastBanner } from '@/components/dashboard/emergency-broadcast-banner';
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
  { href: '/dashboard', label: 'Dashboard', icon: Home, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'] },
  { href: '/dashboard/map', label: 'Community Map', icon: Map, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/map?tab=boundary', label: 'Boundary Manager', icon: ShieldCheck, roles: ['System Admin', 'Admin'] },
  { href: '/dashboard/deals', label: 'Perks & Savings', icon: Gift, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/fundraising', label: 'Fundraising', icon: PiggyBank, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/guidelines', label: 'Guidelines', icon: BookUser, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/directory', label: 'Directory', icon: Users, roles: ['System Admin', 'Admin'] },
  { href: '/dashboard/renters', label: 'Renters', icon: KeyRound, roles: ['Homeowner', 'Admin', 'System Admin'] },
  { href: '/dashboard/visitors', label: 'Visitors', icon: User, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  { href: '/dashboard/gate-pass', label: 'Gate Pass', icon: BadgeCheck, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'] },
  { href: '/dashboard/gate-scanner', label: 'Gate Scanner', icon: Scan, roles: ['System Admin', 'Admin', 'Security'] },
  { href: '/dashboard/calendar', label: 'Calendar', icon: Calendar, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  // Every role, because a notice can now be addressed to any role. The menu
  // previously stopped at Homeowner, so a notice targeted at Security or Staff
  // would have been filtered to them by the server and then hidden by the menu.
  { href: '/dashboard/notifications', label: 'Notifications', icon: Bell, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'] },
  { href: '/dashboard/updates', label: 'Updates', icon: ClipboardList, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  // Everyone on the estate. Temporary Homeowners live here, and Staff are the
  // people most likely to spot the thing an alert is about — both were excluded
  // from a safety feed that any signed-in user can now raise and corroborate.
  { href: '/dashboard/warnings', label: 'Safety Alert', icon: Siren, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'] },
  { href: '/dashboard/block-list', label: 'Block List', icon: ShieldOff, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security'] },
  // Security too: they operate the gate this log records, and the route's
  // `manageSecurity` gate already included them.
  { href: '/dashboard/access-log', label: 'Access Log', icon: ListTree, roles: ['System Admin', 'Admin', 'Security'] },
  // Temporary Homeowners are billed like any other resident, so they get the
  // same entry. Matches the `accessBilling` gate on the routes.
  { href: '/dashboard/billing', label: 'Billing', icon: CreditCard, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/changelog', label: 'App Changelog', icon: History, roles: ['System Admin'] },
  { href: '/dashboard/review-feedback', label: 'Review Feedback', icon: ClipboardCheck, roles: ['System Admin', 'Admin'] },
  { href: '/dashboard/feedback', label: 'Submit Feedback', icon: MessageSquarePlus, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'] },
  { href: '/dashboard/deactivation', label: 'Deactivation', icon: UserX, roles: ['Homeowner', 'Temporary Homeowner'] },
  { href: '/dashboard/settings', label: 'Settings', icon: Settings, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'] },
  { href: '/dashboard/profile', label: 'Profile', icon: User, roles: ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner', 'Security', 'Staff'] },
];

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  // Inertia exposes the current URL on the page object; `usePathname` was a
  // next/navigation hook and has no Inertia equivalent.
  const { url, props } = usePage();
  const pathname = url.split('?')[0];
  const branding = (props as Record<string, any>).branding;
  const communityName = branding?.communityName || branding?.themeTokens?.communityName;

  const { user, loading, logout, justSignedIn } = useAuth();
  const isClient = useIsClient();
  const [isPassOpen, setPassOpen] = useState(false);
  const { isDark, toggle: toggleDark } = useDarkMode();

  const [showSplash, setShowSplash] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    const sessionFlag = sessionStorage.getItem('play_community_splash') === 'true';
    if (sessionFlag) {
      sessionStorage.removeItem('play_community_splash');
      return true;
    }
    return false;
  });

  useEffect(() => {
    if (justSignedIn) {
      setShowSplash(true);
    }
  }, [justSignedIn]);

  /*
   * The original redirected to "/" from the client whenever no user was in
   * context. That guard is gone because it is no longer meaningful: the `auth`
   * middleware rejects the request before this layout renders, and if a session
   * expires mid-visit Laravel returns a redirect that Inertia follows on its
   * own. A client-side check could only ever hide the UI, never the data.
   */


  /*
   * `roles` above is the only thing that decides what a user sees.
   *
   * Two special cases used to sit here and both were wrong. The Staff branch
   * returned a hardcoded [Profile, Gate Pass] and discarded the `roles` arrays
   * entirely — so the deliberate additions of Staff to Notifications and to
   * Safety Alert, each with a comment above it explaining why Staff needed
   * them, were dead configuration that never reached a screen. The Security
   * branch recomputed the same filter under a second name and then appended
   * Profile "if missing", which it never was, because Profile's roles already
   * list Security. It was a no-op guarding against nothing.
   */
  const menuItems = useMemo(
    () => (user ? allMenuItems.filter((item) => item.roles.includes(user.role)) : []),
    [user],
  );

  if (!isClient || loading || !user) {
    return <div className="flex h-screen w-full items-center justify-center">Loading...</div>;
  }

  return (
    /*
     * Provider chain.
     *
     * The Next.js build mounted these in components/providers.tsx via the root
     * layout. Auth, billing and map state are now page props and need no
     * provider; these four remain because each does real work:
     *
     *   TooltipProvider  — Radix Tooltip throws without an ancestor.
     *   ThemeProvider    — holds the optimistic theme state and applies it to
     *                      CSS variables; also loads the selected web font.
     *   BrandingProvider — same, for the estate palette and logo.
     *   Toaster          — useToast() renders nothing without it (below).
     */
    <TooltipProvider delayDuration={200}>
      {showSplash && user && (
        <WelcomeAnimation
          communityName={communityName}
          username={user.displayName || user.name}
          onComplete={() => setShowSplash(false)}
        />
      )}
      <ThemeProvider>
        <BrandingProvider>
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
        <SidebarFooter className="p-3 border-t border-border/40">
          <div className="flex items-center justify-between gap-2 w-full">
            <UserNav />
            <Button
              variant="ghost"
              size="sm"
              onClick={logout}
              className="text-xs text-muted-foreground hover:text-destructive hover:bg-destructive/10 px-2 py-1 h-8 flex items-center gap-1.5 transition-colors"
              title="Log out of Community Hub"
            >
              <LogOut className="h-4 w-4 text-muted-foreground hover:text-destructive" />
              <span className="hidden sm:inline font-medium">Log out</span>
            </Button>
          </div>
        </SidebarFooter>
      </Sidebar>
      <SidebarInset>
        <header className="sticky top-0 z-30 flex h-14 sm:h-16 items-center gap-4 border-b bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/60 sm:px-6 shadow-xs">
          <SidebarTrigger />
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
                  {(user?.role === 'Homeowner' || user?.role === 'Admin' || user?.role === 'System Admin') && (
                    <DropdownMenuItem asChild>
                      <Link href="/dashboard/renters" className="cursor-pointer">
                        <KeyRound className="mr-2 h-4 w-4" />
                        <span>Manage Renters</span>
                      </Link>
                    </DropdownMenuItem>
                  )}
                </DropdownMenuContent>
              </DropdownMenu>

              <Button variant="default" size="icon" className="md:hidden rounded-full h-10 w-10 bg-primary text-primary-foreground shadow-md">
                <Plus className="h-5 w-5" />
              </Button>
            <UserNav />
          </div>
        </header>
        <main key={pathname} className="flex flex-1 flex-col gap-4 p-4 sm:px-6 sm:py-6 md:gap-8 animate-fade-in">
          <EmergencyBroadcastBanner />
          {children}
        </main>
      </SidebarInset>

          <Toaster />
          <FlashToaster />
        </SidebarProvider>
      </BrandingProvider>
      </ThemeProvider>
    </TooltipProvider>
  );
}
