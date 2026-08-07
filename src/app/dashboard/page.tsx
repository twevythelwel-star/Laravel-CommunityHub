'use client';

import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { 
  ArrowUpRight, 
  CalendarCheck, 
  Users, 
  Bell, 
  Siren, 
  DollarSign, 
  Phone, 
  MessageSquare, 
  MapPin, 
  Activity, 
  Shield, 
  Flame, 
  Tv, 
  Sparkles,
  CheckCircle,
  HelpCircle,
  MoreHorizontal
} from "lucide-react";
import { Button } from "@/components/ui/button";
import Link from "next/link";
import Image from "next/image";
import { useAuth } from "@/context/auth-context";
import { ClientFormattedDate } from "@/components/client-formatted-date";
import { useEffect, useState } from "react";
import type { Fundraiser, Donation } from "@/types";
import { FundraiserProgressCard } from "@/components/dashboard/fundraiser-progress-card";

const mockTransactions = [
    { id: '1', homeowner: 'John Smith (Lot 12)', date: '2025-07-20', amount: 5000.00, status: 'Paid' },
    { id: '2', homeowner: 'Emma Watson (Lot 25)', date: '2025-07-19', amount: 5000.00, status: 'Paid' },
    { id: '3', homeowner: 'Michael B. (Lot 03)', date: '2025-07-01', amount: 5000.00, status: 'Overdue' },
    { id: '4', homeowner: 'Olivia Davis (Lot 42)', date: '2025-07-18', amount: 5000.00, status: 'Paid' },
];

const mockPromotions = [
    {
        id: 'promo_1',
        title: 'Free Delivery Friday!',
        description: "From 'Local Eats' tonight only.",
        imageUrl: 'https://picsum.photos/64/64?p=1',
        aiHint: 'food delivery',
    },
    {
        id: 'promo_2',
        title: '50% off Gym Membership',
        description: "Join 'Community Fit' this month.",
        imageUrl: 'https://picsum.photos/64/64?p=2',
        aiHint: 'fitness gym',
    },
    {
        id: 'promo_3',
        title: 'Weekend Car Wash Special',
        description: "Get a full-service wash for $15.",
        imageUrl: 'https://picsum.photos/64/64?p=3',
        aiHint: 'car wash',
    },
];

const mockFundraisers: Fundraiser[] = [
    {
        id: 'fr_1',
        title: 'New Playground Equipment',
        description: 'Help us build a new, modern playground for the community children with the latest safety features.',
        goal: 1500000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-07-01T00:00:00Z'),
        endDate: new Date('2024-09-30T23:59:59Z'),
        status: 'Active',
    },
    {
        id: 'fr_4',
        title: 'Annual Community BBQ',
        description: 'Support our annual community get-together! Funds will go towards food, drinks, and entertainment for all residents.',
        goal: 250000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-08-01T00:00:00Z'),
        endDate: new Date('2024-08-31T23:59:59Z'),
        status: 'Active',
    },
    {
        id: 'fr_2',
        title: 'Community Garden Expansion',
        description: 'We want to add 10 new plots to the community garden and install a new irrigation system.',
        goal: 400000,
        goalCurrency: 'JMD',
        startDate: new Date('2024-10-01T00:00:00Z'),
        endDate: new Date('2024-11-30T23:59:59Z'),
        status: 'Upcoming',
    },
];

const mockDonations: Donation[] = [
    { id: 'd_1', fundraiserId: 'fr_1', amount: 50, currency: 'USD', donorName: 'John S.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_2', fundraiserId: 'fr_1', amount: 100, currency: 'USD', donorName: 'Olivia D.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_3', fundraiserId: 'fr_1', amount: 5000, currency: 'JMD', isAnonymous: true, timestamp: new Date() },
    { id: 'd_4', fundraiserId: 'fr_1', amount: 250, currency: 'USD', donorName: 'Michael B.', isAnonymous: false, timestamp: new Date() },
    { id: 'd_5', fundraiserId: 'fr_1', amount: 75, currency: 'EUR', isAnonymous: true, timestamp: new Date() },
    { id: 'd_7', fundraiserId: 'fr_4', amount: 10000, currency: 'JMD', isAnonymous: true, timestamp: new Date() },
    { id: 'd_8', fundraiserId: 'fr_4', amount: 20, currency: 'USD', donorName: 'Aisha K.', isAnonymous: false, timestamp: new Date() },
];

export default function Dashboard() {
  const { user } = useAuth();
  const [currentMonth, setCurrentMonth] = useState('');
  const [isClient, setIsClient] = useState(false);
  const [activeLocation, setActiveLocation] = useState<string | null>(null);
  const [isGateOpen, setIsGateOpen] = useState(false);

  useEffect(() => {
    setIsClient(true);
    setCurrentMonth(new Date().toLocaleString('default', { month: 'long' }));
  }, [])
  
  if (!isClient || !user) {
    return null;
  }

  const canViewActiveResidents = user && ['System Admin', 'Admin', 'Security'].includes(user.role);
  const canViewUpcomingVisitors = user && ['System Admin', 'Homeowner', 'Temporary Homeowner', 'Security'].includes(user.role);
  const canViewAnnouncements = user && ['System Admin', 'Admin', 'Homeowner'].includes(user.role);
  const canViewWarnings = user && ['System Admin', 'Admin', 'Homeowner', 'Security'].includes(user.role);
  const canViewRecentVisitors = user && ['System Admin', 'Admin', 'Homeowner', 'Security'].includes(user.role);
  const canViewBilling = user && ['System Admin', 'Admin'].includes(user.role);
  const canViewFundraiser = user && ['System Admin', 'Admin', 'Homeowner', 'Temporary Homeowner'].includes(user.role);

  const totalCollected = mockTransactions.filter(t => t.status === 'Paid').reduce((acc, t) => acc + t.amount, 0);
  const outstandingDues = mockTransactions.filter(t => t.status !== 'Paid').reduce((acc, t) => acc + t.amount, 0);
  const activeFundraisers = mockFundraisers.filter(f => f.status === 'Active');

  return (
    <div className="flex flex-1 flex-col gap-8 pb-12 select-none">
      
      {/* ─── PRIMARY LAYOUT GRID (MATCHING SCREENSHOT) ─── */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-5 xl:grid-cols-5">
        
        {/* Neighborhood Map (Left - Spans 3 columns) */}
        <Card className="lg:col-span-3 bg-zinc-950/80 border-zinc-800 text-white flex flex-col justify-between overflow-hidden shadow-2xl relative">
          <div className="p-6 pb-0 flex items-center justify-between z-10">
            <div>
              <CardTitle className="text-lg font-bold tracking-tight">Neighborhood Map</CardTitle>
            </div>
            <div className="flex items-center gap-2">
              <select className="bg-zinc-900 border border-zinc-800 text-zinc-300 rounded px-3 py-1.5 text-xs font-semibold focus:outline-none cursor-pointer hover:border-emerald-500 transition-colors">
                <option>Willow Creek</option>
                <option>Amber Ridge</option>
                <option>Oak Haven</option>
              </select>
              <Button size="icon" variant="ghost" className="h-8 w-8 text-zinc-400 hover:text-white">
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </div>
          </div>

          <CardContent className="p-6 flex flex-1 flex-col md:flex-row gap-6 relative justify-between items-stretch">
            {/* Map sidebar selector list */}
            <div className="flex flex-col justify-center gap-3 w-full md:w-36 shrink-0 z-10">
              <div 
                className={`p-3 rounded-lg border flex items-center gap-3 transition-all duration-300 cursor-pointer ${
                  activeLocation === 'clubhouse' 
                    ? 'bg-emerald-950/40 border-emerald-500/80 text-emerald-400 font-medium' 
                    : 'bg-zinc-900/60 border-zinc-800/80 text-zinc-400 hover:border-zinc-700'
                }`}
                onMouseEnter={() => setActiveLocation('clubhouse')}
                onMouseLeave={() => setActiveLocation(null)}
              >
                <span className="text-lg">🏠</span>
                <span className="text-xs">Clubhouse</span>
              </div>
              <div 
                className={`p-3 rounded-lg border flex items-center gap-3 transition-all duration-300 cursor-pointer ${
                  activeLocation === 'pool' 
                    ? 'bg-emerald-950/40 border-emerald-500/80 text-emerald-400 font-medium' 
                    : 'bg-zinc-900/60 border-zinc-800/80 text-zinc-400 hover:border-zinc-700'
                }`}
                onMouseEnter={() => setActiveLocation('pool')}
                onMouseLeave={() => setActiveLocation(null)}
              >
                <span className="text-lg">🏊</span>
                <span className="text-xs">Pool</span>
              </div>
              <div 
                className={`p-3 rounded-lg border flex items-center gap-3 transition-all duration-300 cursor-pointer ${
                  activeLocation === 'park' 
                    ? 'bg-emerald-950/40 border-emerald-500/80 text-emerald-400 font-medium' 
                    : 'bg-zinc-900/60 border-zinc-800/80 text-zinc-400 hover:border-zinc-700'
                }`}
                onMouseEnter={() => setActiveLocation('park')}
                onMouseLeave={() => setActiveLocation(null)}
              >
                <span className="text-lg">🌳</span>
                <span className="text-xs">Park</span>
              </div>
              <div 
                className={`p-3 rounded-lg border flex items-center gap-3 transition-all duration-300 cursor-pointer ${
                  activeLocation === 'gym' 
                    ? 'bg-emerald-950/40 border-emerald-500/80 text-emerald-400 font-medium' 
                    : 'bg-zinc-900/60 border-zinc-800/80 text-zinc-400 hover:border-zinc-700'
                }`}
                onMouseEnter={() => setActiveLocation('gym')}
                onMouseLeave={() => setActiveLocation(null)}
              >
                <span className="text-lg">🏋️</span>
                <span className="text-xs">Gym</span>
              </div>
            </div>

            {/* Custom SVG street map vector graphic */}
            <div className="flex-1 min-h-[220px] bg-zinc-950 rounded-2xl border border-zinc-900 p-2 relative flex items-center justify-center overflow-hidden">
              <svg className="w-full h-full max-h-[300px]" viewBox="0 0 400 300" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                  <filter id="map-glow-svg" x="-20%" y="-20%" width="140%" height="140%">
                    <feGaussianBlur stdDeviation="5" result="blur" />
                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                  </filter>
                </defs>

                {/* Land plots (background shapes) */}
                <path d="M10 10 H390 V290 H10 Z" fill="#0c0c0e" />
                <path d="M20 20 C60 20, 100 60, 100 110 C100 160, 40 200, 20 240 Z" fill="#082f25" opacity="0.25" />
                <path d="M220 30 C300 20, 360 80, 370 150 C380 220, 310 270, 260 270 Z" fill="#082f25" opacity="0.15" />
                <path d="M140 180 C180 180, 200 220, 180 270 C160 290, 120 280, 110 250 Z" fill="#082f25" opacity="0.2" />

                {/* Street map road vectors */}
                <path d="M 50,0 Q 80,120 50,220 T 120,290" stroke="#1f1f23" strokeWidth="12" strokeLinecap="round" strokeLinejoin="round" fill="none" />
                <path d="M 50,80 Q 220,50 350,110 T 320,250" stroke="#1f1f23" strokeWidth="12" strokeLinecap="round" strokeLinejoin="round" fill="none" />
                <path d="M 120,110 Q 180,190 280,180" stroke="#1f1f23" strokeWidth="10" strokeLinecap="round" fill="none" />
                <path d="M 280,68 L 280,180" stroke="#1f1f23" strokeWidth="10" strokeLinejoin="round" fill="none" />
                
                {/* Active Highlighted Road Path */}
                <path d="M 50,80 Q 220,50 350,110 T 320,250" stroke="#00e5a0" strokeWidth="3" strokeLinecap="round" strokeDasharray="6 4" fill="none" opacity="0.8" filter="url(#map-glow-svg)" />

                {/* Location Points/Pins */}
                {/* Clubhouse */}
                <g 
                  className="cursor-pointer group"
                  onMouseEnter={() => setActiveLocation('clubhouse')}
                  onMouseLeave={() => setActiveLocation(null)}
                >
                  <circle cx="85" cy="65" r="14" fill="#00e5a0" opacity={activeLocation === 'clubhouse' ? 0.35 : 0.15} className="transition-all duration-300" />
                  <circle cx="85" cy="65" r="6" fill="#00e5a0" />
                  <text x="85" y="45" fill={activeLocation === 'clubhouse' ? '#00e5a0' : '#a1a1aa'} fontSize="8" fontWeight="bold" textAnchor="middle">🏠 Clubhouse</text>
                </g>

                {/* Pool */}
                <g 
                  className="cursor-pointer group"
                  onMouseEnter={() => setActiveLocation('pool')}
                  onMouseLeave={() => setActiveLocation(null)}
                >
                  <circle cx="160" cy="140" r="14" fill="#00e5a0" opacity={activeLocation === 'pool' ? 0.35 : 0.15} className="transition-all duration-300" />
                  <circle cx="160" cy="140" r="6" fill="#00e5a0" />
                  <text x="160" y="122" fill={activeLocation === 'pool' ? '#00e5a0' : '#a1a1aa'} fontSize="8" fontWeight="bold" textAnchor="middle">🏊 Pool</text>
                </g>

                {/* Gym */}
                <g 
                  className="cursor-pointer group"
                  onMouseEnter={() => setActiveLocation('gym')}
                  onMouseLeave={() => setActiveLocation(null)}
                >
                  <circle cx="280" cy="115" r="14" fill="#00e5a0" opacity={activeLocation === 'gym' ? 0.35 : 0.15} className="transition-all duration-300" />
                  <circle cx="280" cy="115" r="6" fill="#00e5a0" />
                  <text x="280" y="97" fill={activeLocation === 'gym' ? '#00e5a0' : '#a1a1aa'} fontSize="8" fontWeight="bold" textAnchor="middle">🏋️ Gym</text>
                </g>

                {/* Park */}
                <g 
                  className="cursor-pointer group"
                  onMouseEnter={() => setActiveLocation('park')}
                  onMouseLeave={() => setActiveLocation(null)}
                >
                  <circle cx="220" cy="220" r="14" fill="#00e5a0" opacity={activeLocation === 'park' ? 0.35 : 0.15} className="transition-all duration-300" />
                  <circle cx="220" cy="220" r="6" fill="#00e5a0" />
                  <text x="220" y="202" fill={activeLocation === 'park' ? '#00e5a0' : '#a1a1aa'} fontSize="8" fontWeight="bold" textAnchor="middle">🌳 Park</text>
                </g>

                {/* Active Gate Indicator Node */}
                <g className="cursor-pointer" onClick={() => setIsGateOpen(!isGateOpen)}>
                  <circle cx="340" cy="210" r="16" fill={isGateOpen ? '#00e5a0' : '#ef4444'} opacity="0.25" className="animate-pulse" />
                  <circle cx="340" cy="210" r="7" fill={isGateOpen ? '#00e5a0' : '#ef4444'} />
                  <text x="340" y="235" fill={isGateOpen ? '#00e5a0' : '#ef4444'} fontSize="8" fontWeight="bold" textAnchor="middle">🛡️ Security Gate</text>
                </g>
              </svg>
            </div>
          </CardContent>

          {/* Bottom Action Area */}
          <div className="p-6 pt-0 border-t border-zinc-900/60 flex items-center justify-between bg-zinc-950/30">
            <span className="text-xs text-zinc-400 flex items-center gap-1.5">
              <span className="h-2 w-2 rounded-full bg-emerald-400 inline-block animate-pulse"></span>
              All surveillance nodes online
            </span>
            <Button 
              size="sm" 
              onClick={() => setIsGateOpen(!isGateOpen)}
              className={`text-xs font-semibold h-8 rounded-lg gap-1.5 transition-all duration-300 ${
                isGateOpen 
                  ? 'bg-emerald-500 hover:bg-emerald-600 text-zinc-950 shadow-md shadow-emerald-500/20' 
                  : 'bg-zinc-900 border border-zinc-800 text-emerald-400 hover:bg-zinc-800'
              }`}
            >
              <Activity className="h-3.5 w-3.5" />
              <span>{isGateOpen ? 'Gate Opened' : 'Security Gate Locked'}</span>
            </Button>
          </div>
        </Card>

        {/* Community Announcements (Right - Spans 2 columns) */}
        <Card className="lg:col-span-2 bg-zinc-950/80 border-zinc-800 text-white flex flex-col justify-between overflow-hidden shadow-2xl">
          <div className="p-6 pb-0 flex items-center justify-between">
            <CardTitle className="text-lg font-bold tracking-tight">Community Announcements</CardTitle>
            <Button size="icon" variant="ghost" className="h-8 w-8 text-zinc-400 hover:text-white">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </div>

          <CardContent className="p-6 flex-1 flex flex-col gap-4 overflow-y-auto max-h-[360px] scrollbar-thin scrollbar-thumb-zinc-800">
            
            {/* BBQ Announcement Item */}
            <div className="bg-zinc-900/50 border border-zinc-800/80 rounded-xl overflow-hidden flex flex-col hover:border-zinc-700 transition-colors">
              <div className="h-24 bg-zinc-850 relative overflow-hidden shrink-0">
                <Image 
                  src="https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=400&q=80" 
                  alt="Annual BBQ Event"
                  fill
                  className="object-cover opacity-80"
                />
                <div className="absolute top-3 left-3 bg-zinc-950/95 border border-zinc-800/80 rounded px-2 py-1 flex flex-col items-center shrink-0 min-w-[42px] leading-tight shadow-md">
                  <span className="text-[10px] text-zinc-400 font-bold uppercase tracking-wider">Nov</span>
                  <span className="text-sm font-extrabold text-emerald-400">15</span>
                </div>
              </div>
              <div className="p-4 flex flex-col gap-1">
                <h4 className="text-sm font-bold text-white leading-tight">Annual BBQ Event</h4>
                <p className="text-xs text-zinc-400 line-clamp-2">
                  Annual BBQ Event starts tonight! Join us for a beautiful community evening filled with family entertainment, local food trucks, and friendly games...
                </p>
                <div className="flex items-center justify-between mt-3 pt-2.5 border-t border-zinc-850">
                  <div className="flex items-center gap-1.5">
                    <div className="h-5 w-5 rounded-full bg-zinc-800 relative overflow-hidden border border-zinc-750">
                      <Image src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=64&q=80" alt="Admin Profile" fill className="object-cover" />
                    </div>
                    <span className="text-[10px] text-zinc-400 font-medium">Olivia Davis (Lot 42)</span>
                  </div>
                  <div className="flex items-center gap-3 text-[10px] text-zinc-400 font-semibold">
                    <span className="flex items-center gap-1 text-emerald-400"><MessageSquare className="h-3 w-3" /> 5</span>
                    <span className="flex items-center gap-1 text-zinc-400"><Flame className="h-3 w-3 text-red-500" /> 12</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Security Announcement Item */}
            <div className="bg-zinc-900/50 border border-zinc-800/80 rounded-xl p-4 flex gap-3.5 hover:border-zinc-700 transition-colors">
              <div className="bg-emerald-950/40 border border-emerald-900/60 rounded-xl h-10 w-10 shrink-0 flex items-center justify-center text-emerald-400">
                <Shield className="h-5 w-5" />
              </div>
              <div className="flex-1 flex flex-col gap-1">
                <div className="flex items-center justify-between leading-none mb-0.5">
                  <h4 className="text-sm font-bold text-white">Security Update</h4>
                  <span className="text-[9px] text-zinc-500 font-bold uppercase tracking-wider">Nov 15</span>
                </div>
                <p className="text-xs text-zinc-400">
                  Security patrol coordinates description. Recommended security update guidelines have been deployed on all gate panels.
                </p>
                <div className="flex items-center justify-between mt-2 pt-2 border-t border-zinc-850">
                  <span className="text-[10px] text-zinc-500">Board Announcement</span>
                  <div className="flex items-center gap-3 text-[10px] text-zinc-400 font-semibold">
                    <span className="flex items-center gap-1 text-emerald-400"><MessageSquare className="h-3 w-3" /> 2</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Fitness Class Announcement Item */}
            <div className="bg-zinc-900/50 border border-zinc-800/80 rounded-xl overflow-hidden flex flex-col hover:border-zinc-700 transition-colors">
              <div className="h-20 bg-zinc-850 relative overflow-hidden shrink-0">
                <Image 
                  src="https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=400&q=80" 
                  alt="Fitness Class"
                  fill
                  className="object-cover opacity-80"
                />
                <div className="absolute top-2.5 left-2.5 bg-zinc-950/95 border border-zinc-800/80 rounded px-2 py-0.5 flex flex-col items-center shrink-0 min-w-[38px] leading-tight shadow-md">
                  <span className="text-[9px] text-zinc-400 font-bold uppercase tracking-wider">Nov</span>
                  <span className="text-xs font-extrabold text-emerald-400">14</span>
                </div>
              </div>
              <div className="p-4 flex flex-col gap-1">
                <h4 className="text-sm font-bold text-white leading-tight">Fitness Class</h4>
                <p className="text-xs text-zinc-400 line-clamp-1">
                  Lorem ipsum dolor sit amet, fitness class descriptions are released and bookings are now open.
                </p>
                <div className="flex items-center justify-between mt-2.5 pt-2 border-t border-zinc-850">
                  <span className="text-[10px] text-zinc-500">Instructor Alex</span>
                  <div className="flex items-center gap-3 text-[10px] text-zinc-400 font-semibold">
                    <span className="flex items-center gap-1 text-emerald-400"><MessageSquare className="h-3 w-3" /> 8</span>
                  </div>
                </div>
              </div>
            </div>

          </CardContent>
        </Card>

      </div>

      {/* ─── SECOND ROW: VISITOR GATE PASSES & RESIDENT DIRECTORY ─── */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-5 xl:grid-cols-5">
        
        {/* Visitor Gate Passes (Spans 3 Columns) */}
        <Card className="lg:col-span-3 bg-zinc-950/80 border-zinc-800 text-white flex flex-col justify-between overflow-hidden shadow-2xl">
          <div className="p-6 pb-0 flex items-center justify-between">
            <div>
              <CardTitle className="text-lg font-bold tracking-tight">Visitor Gate Passes</CardTitle>
            </div>
            <Button size="icon" variant="ghost" className="h-8 w-8 text-zinc-400 hover:text-white">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </div>

          <CardContent className="p-6 flex flex-col md:flex-row gap-4 overflow-x-auto">
            {/* Pass 1 */}
            <div className="flex-1 min-w-[200px] bg-zinc-900/60 border border-zinc-800 rounded-xl p-4 flex flex-col gap-4 hover:border-emerald-500/40 transition-all duration-300 relative group">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-full bg-zinc-800 relative overflow-hidden border border-zinc-700">
                  <Image src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=80&q=80" alt="Liam Johnson" fill className="object-cover" />
                </div>
                <div className="flex-1 flex flex-col leading-tight">
                  <span className="text-xs font-bold text-white group-hover:text-emerald-400 transition-colors">Liam Johnson</span>
                  <span className="text-[10px] text-zinc-500">Lot 42 Guest</span>
                </div>
                <span className="h-2 w-2 rounded-full bg-emerald-400 shrink-0"></span>
              </div>
              <div className="flex flex-col gap-1.5 text-[10px] text-zinc-400 font-semibold border-t border-b border-zinc-850 py-3">
                <div className="flex justify-between"><span className="text-zinc-500">Arrival</span><span>09:45 AM</span></div>
                <div className="flex justify-between"><span className="text-zinc-500">Duration</span><span>3 hrs</span></div>
                <div className="flex justify-between"><span className="text-zinc-500">Vehicle</span><span>Tesla Model S</span></div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-[9px] font-bold text-emerald-400 bg-emerald-950/30 border border-emerald-900/40 rounded-full px-2 py-0.5">● Approved</span>
                {/* Visual SVG QR Code */}
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" className="text-zinc-400 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity">
                  <rect x="0" y="0" width="28" height="28" fill="none" />
                  <path d="M2 2 H10 V10 H2 Z M4 4 H8 V8 H4 Z" fill="currentColor" />
                  <path d="M18 2 H26 V10 H18 Z M20 4 H24 V8 H20 Z" fill="currentColor" />
                  <path d="M2 18 H10 V26 H2 Z M4 20 H8 V24 H4 Z" fill="currentColor" />
                  <rect x="14" y="14" width="4" height="4" fill="currentColor" />
                  <rect x="22" y="14" width="4" height="2" fill="currentColor" />
                  <rect x="14" y="22" width="2" height="4" fill="currentColor" />
                  <rect x="20" y="20" width="6" height="2" fill="currentColor" />
                  <rect x="18" y="24" width="4" height="2" fill="currentColor" />
                </svg>
              </div>
            </div>

            {/* Pass 2 */}
            <div className="flex-1 min-w-[200px] bg-zinc-900/60 border border-zinc-800 rounded-xl p-4 flex flex-col gap-4 hover:border-emerald-500/40 transition-all duration-300 relative group">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-full bg-zinc-800 relative overflow-hidden border border-zinc-700">
                  <Image src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=80&q=80" alt="Noah Williams" fill className="object-cover" />
                </div>
                <div className="flex-1 flex flex-col leading-tight">
                  <span className="text-xs font-bold text-white group-hover:text-emerald-400 transition-colors">Noah Williams</span>
                  <span className="text-[10px] text-zinc-500">Lot 12 Guest</span>
                </div>
                <span className="h-2 w-2 rounded-full bg-emerald-400 shrink-0"></span>
              </div>
              <div className="flex flex-col gap-1.5 text-[10px] text-zinc-400 font-semibold border-t border-b border-zinc-850 py-3">
                <div className="flex justify-between"><span className="text-zinc-500">Arrival</span><span>10:30 AM</span></div>
                <div className="flex justify-between"><span className="text-zinc-500">Duration</span><span>2 hrs</span></div>
                <div className="flex justify-between"><span className="text-zinc-500">Vehicle</span><span>XYZ 1234</span></div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-[9px] font-bold text-emerald-400 bg-emerald-950/30 border border-emerald-900/40 rounded-full px-2 py-0.5">● Approved</span>
                {/* Visual SVG QR Code */}
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" className="text-zinc-400 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity">
                  <rect x="0" y="0" width="28" height="28" fill="none" />
                  <path d="M2 2 H10 V10 H2 Z M4 4 H8 V8 H4 Z" fill="currentColor" />
                  <path d="M18 2 H26 V10 H18 Z M20 4 H24 V8 H20 Z" fill="currentColor" />
                  <path d="M2 18 H10 V26 H2 Z M4 20 H8 V24 H4 Z" fill="currentColor" />
                  <rect x="14" y="14" width="4" height="4" fill="currentColor" />
                  <rect x="22" y="14" width="4" height="2" fill="currentColor" />
                  <rect x="14" y="22" width="2" height="4" fill="currentColor" />
                  <rect x="20" y="20" width="6" height="2" fill="currentColor" />
                  <rect x="18" y="24" width="4" height="2" fill="currentColor" />
                </svg>
              </div>
            </div>

            {/* Pass 3 */}
            <div className="flex-1 min-w-[200px] bg-zinc-900/60 border border-zinc-800 rounded-xl p-4 flex flex-col gap-4 hover:border-emerald-500/40 transition-all duration-300 relative group">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-full bg-zinc-800 relative overflow-hidden border border-zinc-700">
                  <Image src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=80&q=80" alt="Emma Watson" fill className="object-cover" />
                </div>
                <div className="flex-1 flex flex-col leading-tight">
                  <span className="text-xs font-bold text-white group-hover:text-emerald-400 transition-colors">Emma Watson</span>
                  <span className="text-[10px] text-zinc-500">Lot 25 Guest</span>
                </div>
                <span className="h-2 w-2 rounded-full bg-emerald-400 shrink-0"></span>
              </div>
              <div className="flex flex-col gap-1.5 text-[10px] text-zinc-400 font-semibold border-t border-b border-zinc-850 py-3">
                <div className="flex justify-between"><span className="text-zinc-500">Arrival</span><span>01:15 PM</span></div>
                <div className="flex justify-between"><span className="text-zinc-500">Duration</span><span>4 hrs</span></div>
                <div className="flex justify-between"><span className="text-zinc-500">Vehicle</span><span>Toyota RAV4</span></div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-[9px] font-bold text-emerald-400 bg-emerald-950/30 border border-emerald-900/40 rounded-full px-2 py-0.5">● Approved</span>
                {/* Visual SVG QR Code */}
                <svg width="28" height="28" viewBox="0 0 28 28" fill="none" className="text-zinc-400 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity">
                  <rect x="0" y="0" width="28" height="28" fill="none" />
                  <path d="M2 2 H10 V10 H2 Z M4 4 H8 V8 H4 Z" fill="currentColor" />
                  <path d="M18 2 H26 V10 H18 Z M20 4 H24 V8 H20 Z" fill="currentColor" />
                  <path d="M2 18 H10 V26 H2 Z M4 20 H8 V24 H4 Z" fill="currentColor" />
                  <rect x="14" y="14" width="4" height="4" fill="currentColor" />
                  <rect x="22" y="14" width="4" height="2" fill="currentColor" />
                  <rect x="14" y="22" width="2" height="4" fill="currentColor" />
                  <rect x="20" y="20" width="6" height="2" fill="currentColor" />
                  <rect x="18" y="24" width="4" height="2" fill="currentColor" />
                </svg>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Resident Directory (Spans 2 Columns) */}
        <Card className="lg:col-span-2 bg-zinc-950/80 border-zinc-800 text-white flex flex-col justify-between overflow-hidden shadow-2xl">
          <div className="p-6 pb-0 flex items-center justify-between">
            <CardTitle className="text-lg font-bold tracking-tight">Resident Directory</CardTitle>
            <Button size="icon" variant="ghost" className="h-8 w-8 text-zinc-400 hover:text-white">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </div>

          <CardContent className="p-6 flex-1 grid grid-cols-2 gap-3 overflow-y-auto max-h-[300px] scrollbar-thin scrollbar-thumb-zinc-800">
            {/* Resident 1 */}
            <div className="bg-zinc-900/40 border border-zinc-850 rounded-xl p-3 flex flex-col gap-2 hover:border-zinc-700 transition-colors">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-zinc-800 relative overflow-hidden border border-zinc-700">
                  <Image src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=64&q=80" alt="Sarah J." fill className="object-cover" />
                </div>
                <div className="flex-1 min-w-0">
                  <h5 className="text-xs font-bold text-white truncate">Sarah J.</h5>
                  <p className="text-[9px] text-zinc-500 font-semibold uppercase">Lot 12 · Res</p>
                </div>
              </div>
              <div className="flex gap-1.5 mt-1 border-t border-zinc-850 pt-2 justify-end">
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-zinc-850/50 hover:bg-emerald-500 hover:text-zinc-950 text-zinc-400 transition-all duration-300">
                  <Phone className="h-3 w-3" />
                </Button>
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-zinc-850/50 hover:bg-emerald-500 hover:text-zinc-950 text-zinc-400 transition-all duration-300">
                  <MessageSquare className="h-3 w-3" />
                </Button>
              </div>
            </div>

            {/* Resident 2 */}
            <div className="bg-zinc-900/40 border border-zinc-850 rounded-xl p-3 flex flex-col gap-2 hover:border-zinc-700 transition-colors">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-zinc-800 relative overflow-hidden border border-zinc-700">
                  <Image src="https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=64&q=80" alt="David M." fill className="object-cover" />
                </div>
                <div className="flex-1 min-w-0">
                  <h5 className="text-xs font-bold text-white truncate">David M.</h5>
                  <p className="text-[9px] text-emerald-400 font-bold uppercase">Lot 42 · Board</p>
                </div>
              </div>
              <div className="flex gap-1.5 mt-1 border-t border-zinc-850 pt-2 justify-end">
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-zinc-850/50 hover:bg-emerald-500 hover:text-zinc-950 text-zinc-400 transition-all duration-300">
                  <Phone className="h-3 w-3" />
                </Button>
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-zinc-850/50 hover:bg-emerald-500 hover:text-zinc-950 text-zinc-400 transition-all duration-300">
                  <MessageSquare className="h-3 w-3" />
                </Button>
              </div>
            </div>

            {/* Resident 3 */}
            <div className="bg-zinc-900/40 border border-zinc-850 rounded-xl p-3 flex flex-col gap-2 hover:border-zinc-700 transition-colors">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-zinc-800 relative overflow-hidden border border-zinc-700">
                  <Image src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=64&q=80" alt="Andrew K." fill className="object-cover" />
                </div>
                <div className="flex-1 min-w-0">
                  <h5 className="text-xs font-bold text-white truncate">Andrew K.</h5>
                  <p className="text-[9px] text-zinc-500 font-semibold uppercase">Lot 03 · Res</p>
                </div>
              </div>
              <div className="flex gap-1.5 mt-1 border-t border-zinc-850 pt-2 justify-end">
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-zinc-850/50 hover:bg-emerald-500 hover:text-zinc-950 text-zinc-400 transition-all duration-300">
                  <Phone className="h-3 w-3" />
                </Button>
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-zinc-850/50 hover:bg-emerald-500 hover:text-zinc-950 text-zinc-400 transition-all duration-300">
                  <MessageSquare className="h-3 w-3" />
                </Button>
              </div>
            </div>

            {/* Resident 4 */}
            <div className="bg-zinc-900/40 border border-zinc-850 rounded-xl p-3 flex flex-col gap-2 hover:border-zinc-700 transition-colors">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-zinc-800 relative overflow-hidden border border-zinc-700">
                  <Image src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=64&q=80" alt="Elena R." fill className="object-cover" />
                </div>
                <div className="flex-1 min-w-0">
                  <h5 className="text-xs font-bold text-white truncate">Elena R.</h5>
                  <p className="text-[9px] text-zinc-500 font-semibold uppercase">Lot 25 · Res</p>
                </div>
              </div>
              <div className="flex gap-1.5 mt-1 border-t border-zinc-850 pt-2 justify-end">
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-zinc-850/50 hover:bg-emerald-500 hover:text-zinc-950 text-zinc-400 transition-all duration-300">
                  <Phone className="h-3 w-3" />
                </Button>
                <Button size="icon" variant="ghost" className="h-6 w-6 rounded-md bg-zinc-850/50 hover:bg-emerald-500 hover:text-zinc-950 text-zinc-400 transition-all duration-300">
                  <MessageSquare className="h-3 w-3" />
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

      </div>

      {/* ─── PREVIOUS LAYOUT FEATURES & DATA WIDGETS (BOTTOM AREA) ─── */}
      <div className="border-t border-zinc-800/60 pt-8 mt-4">
        
        {/* Title for the administrative and operations deck */}
        <div className="mb-6 flex items-center gap-2">
          <Badge className="bg-emerald-950/50 border border-emerald-900 text-emerald-400 hover:bg-emerald-950/50">
            <Activity className="h-3 w-3 mr-1" />
            Operations Portal
          </Badge>
          <h2 className="text-xl font-bold tracking-tight text-white">Financials, Fundraisers & Promotions</h2>
        </div>

        {/* Previous summary statistics (Only renders if canViewActiveResidents, canViewBilling, etc are true) */}
        <div className="grid gap-4 md:grid-cols-2 md:gap-8 lg:grid-cols-4 mb-8">
          {canViewActiveResidents && (
            <Card className="bg-zinc-950/40 border-zinc-800 text-white">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                  Active Residents
                </CardTitle>
                <Users className="h-4 w-4 text-emerald-400" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-extrabold text-white">452</div>
                <p className="text-[10px] text-zinc-500 mt-1">
                  +12 from last month
                </p>
              </CardContent>
            </Card>
          )}

          {canViewBilling && (
            <>
              <Card className="bg-zinc-950/40 border-zinc-800 text-white">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                    Total Collected ({currentMonth})
                  </CardTitle>
                  <DollarSign className="h-4 w-4 text-emerald-400" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-extrabold text-white">
                    JMD {totalCollected.toLocaleString('en-JM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                  <p className="text-[10px] text-zinc-500 mt-1">
                    from {mockTransactions.filter(t => t.status === 'Paid').length} households
                  </p>
                </CardContent>
              </Card>

              <Card className="bg-zinc-950/40 border-zinc-800 text-white">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                    Outstanding Dues
                  </CardTitle>
                  <DollarSign className="h-4 w-4 text-zinc-400" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-extrabold text-white">
                    JMD {outstandingDues.toLocaleString('en-JM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                  <p className="text-[10px] text-zinc-500 mt-1">
                    from {mockTransactions.filter(t => t.status !== 'Paid').length} household
                  </p>
                </CardContent>
              </Card>
            </>
          )}

          {canViewUpcomingVisitors && (
            <Card className="bg-zinc-950/40 border-zinc-800 text-white">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-xs font-semibold text-zinc-400 uppercase tracking-wider">
                  Upcoming Visitors
                </CardTitle>
                <CalendarCheck className="h-4 w-4 text-emerald-400" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-extrabold text-white">15</div>
                <p className="text-[10px] text-zinc-500 mt-1">
                  +3 scheduled today
                </p>
              </CardContent>
            </Card>
          )}
        </div>

        {/* Previous Fundraiser Cards Section */}
        {canViewFundraiser && (
          <div className="grid gap-6 md:grid-cols-2 mb-8">
            {activeFundraisers.map((fundraiser) => (
              <FundraiserProgressCard 
                key={fundraiser.id}
                fundraiser={fundraiser} 
                donations={mockDonations.filter(d => d.fundraiserId === fundraiser.id)} 
              />
            ))}
          </div>
        )}

        {/* Bottom tables row (Recent Visitors, Billing Transactions, and Promotions) */}
        <div className="grid gap-6 md:gap-8 lg:grid-cols-2 xl:grid-cols-3">
          
          {canViewRecentVisitors && (
            <Card className="xl:col-span-2 bg-zinc-950/40 border-zinc-800 text-white shadow-xl">
              <CardHeader className="flex flex-row items-center">
                <div className="grid gap-1">
                  <CardTitle className="text-base font-bold">Recent Visitors Log</CardTitle>
                  <CardDescription className="text-xs text-zinc-500">
                    A log of the most recent visitors approved at the gate.
                  </CardDescription>
                </div>
                <Button asChild size="sm" variant="outline" className="ml-auto gap-1 border-zinc-800 bg-zinc-900 text-zinc-300 hover:text-white hover:bg-zinc-850">
                  <Link href="/dashboard/visitors">
                    View All
                    <ArrowUpRight className="h-3.5 w-3.5" />
                  </Link>
                </Button>
              </CardHeader>
              <CardContent>
                <Table className="text-zinc-300">
                  <TableHeader className="border-zinc-800/80">
                    <TableRow className="border-zinc-800 hover:bg-transparent">
                      <TableHead className="text-zinc-400 font-semibold">Visitor</TableHead>
                      <TableHead className="hidden md:table-cell text-zinc-400 font-semibold">Homeowner</TableHead>
                      <TableHead className="text-right text-zinc-400 font-semibold">Date</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow className="border-zinc-850 hover:bg-zinc-900/30">
                      <TableCell>
                        <div className="font-semibold text-sm">Liam Johnson</div>
                        <div className="text-xs text-zinc-500">liam@example.com</div>
                      </TableCell>
                      <TableCell className="hidden md:table-cell text-xs text-zinc-400">
                        Olivia Davis (Lot 42)
                      </TableCell>
                      <TableCell className="text-right text-xs"><ClientFormattedDate date={new Date("2025-06-23")} formatString="yyyy-MM-dd" /></TableCell>
                    </TableRow>
                    <TableRow className="border-zinc-850 hover:bg-zinc-900/30">
                      <TableCell>
                        <div className="font-semibold text-sm">Noah Williams</div>
                        <div className="text-xs text-zinc-500">noah@example.com</div>
                      </TableCell>
                      <TableCell className="hidden md:table-cell text-xs text-zinc-400">
                        John Smith (Lot 12)
                      </TableCell>
                      <TableCell className="text-right text-xs"><ClientFormattedDate date={new Date("2025-06-24")} formatString="yyyy-MM-dd" /></TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}

          {canViewBilling && (
            <Card className="bg-zinc-950/40 border-zinc-800 text-white shadow-xl">
              <CardHeader className="flex flex-row items-center">
                <div className="grid gap-1">
                  <CardTitle className="text-base font-bold">Recent Payments</CardTitle>
                  <CardDescription className="text-xs text-zinc-500">
                    A log of maintenance fees and dues.
                  </CardDescription>
                </div>
                <Button asChild size="sm" variant="outline" className="ml-auto gap-1 border-zinc-800 bg-zinc-900 text-zinc-300 hover:text-white hover:bg-zinc-850">
                  <Link href="/dashboard/billing">
                    View All
                    <ArrowUpRight className="h-3.5 w-3.5" />
                  </Link>
                </Button>
              </CardHeader>
              <CardContent>
                <Table className="text-zinc-300">
                  <TableHeader className="border-zinc-800/80">
                    <TableRow className="border-zinc-800 hover:bg-transparent">
                      <TableHead className="text-zinc-400 font-semibold">Homeowner</TableHead>
                      <TableHead className="text-zinc-400 font-semibold">Status</TableHead>
                      <TableHead className="text-right text-zinc-400 font-semibold">Amount (JMD)</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {mockTransactions.slice(0,3).map(transaction => (
                      <TableRow key={transaction.id} className="border-zinc-850 hover:bg-zinc-900/30">
                        <TableCell>
                          <div className="font-semibold text-sm">{transaction.homeowner.split('(')[0].trim()}</div>
                          <div className="text-xs text-zinc-500">
                            {transaction.homeowner.match(/\(([^)]+)\)/)?.[1]}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge className="text-[10px]" variant={transaction.status === 'Paid' ? 'secondary' : 'destructive'}>
                            {transaction.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right font-mono text-xs">{transaction.amount.toFixed(2)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}

          <Card className="bg-zinc-950/40 border-zinc-800 text-white shadow-xl">
            <CardHeader>
              <CardTitle className="text-base font-bold">Community Promotions</CardTitle>
              <CardDescription className="text-xs text-zinc-500">
                Special offers from local businesses for our residents.
              </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
              {mockPromotions.map((promo) => (
                <div key={promo.id} className="flex items-center gap-4 group p-1.5 rounded-lg hover:bg-zinc-900/40 transition-colors">
                  <div className="h-12 w-12 rounded-lg overflow-hidden relative border border-zinc-800 shrink-0">
                    <Image 
                      alt={promo.title} 
                      className="object-cover" 
                      fill 
                      src={promo.imageUrl} 
                      data-ai-hint={promo.aiHint}
                    />
                  </div>
                  <div className="grid gap-0.5">
                    <p className="text-xs font-bold leading-snug group-hover:text-emerald-400 transition-colors">
                      {promo.title}
                    </p>
                    <p className="text-[10px] text-zinc-500 leading-normal">
                      {promo.description}
                    </p>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

        </div>

      </div>

    </div>
  );
}
