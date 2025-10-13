
'use client';

import { useState, useEffect } from 'react';
import QRCode from "react-qr-code";
import { Avatar, AvatarFallback, AvatarImage } from '../ui/avatar';
import { Badge } from '../ui/badge';
import { Smartphone, Apple } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useIsClient } from '@/hooks/use-is-client';
import { useAuth } from '@/context/auth-context';
import type { UserRole } from '@/types';

const highContrastColors = [
  "#000000", // Black
  "#083344", // Dark Cyan
  "#4A044E", // Dark Magenta
  "#701A75", // Dark Fuchsia
  "#004225", // Dark Green
  "#4B0082", // Indigo
  "#8B0000", // Dark Red
];

function getOperatingSystem(): 'iOS' | 'Android' | 'Other' {
    if (typeof window === 'undefined') return 'Other';
    const userAgent = window.navigator.userAgent;
    if (/iPad|iPhone|iPod/.test(userAgent)) return 'iOS';
    if (/Android/.test(userAgent)) return 'Android';
    return 'Other';
}

const getPassGradient = (role: UserRole): string => {
    switch (role) {
        case 'System Admin':
        case 'Admin':
            return 'from-sky-400 to-blue-600';
        case 'Homeowner':
            return 'from-primary/80 to-accent/80';
        case 'Temporary Homeowner':
            return 'from-purple-400 to-indigo-500';
        case 'Security':
        case 'Staff':
            return 'from-slate-400 to-slate-600';
        default:
            return 'from-primary/80 to-accent/80';
    }
}

export function GatePassDisplay() {
  const { user } = useAuth();
  const isClient = useIsClient();
  const [os, setOs] = useState<'iOS' | 'Android' | 'Other'>('Other');
  const [qrColor, setQrColor] = useState(highContrastColors[0]);
  
  useEffect(() => {
    if (isClient) {
        setOs(getOperatingSystem());
    }
  }, [isClient]);

  useEffect(() => {
      const randomIndex = Math.floor(Math.random() * highContrastColors.length);
      setQrColor(highContrastColors[randomIndex]);
  }, []);

  const getSimulatedJwsToken = () => {
    if (!user) return 'invalid-user';
    const header = btoa(JSON.stringify({ alg: 'EdDSA', typ: 'JWT' }));
    const payload = btoa(JSON.stringify({
        sub: `user:${user.uid}`,
        name: user.displayName,
        role: user.role,
        iat: Math.floor(Date.now() / 1000),
        exp: Math.floor(Date.now() / 1000) + 60, // Expires in 60 seconds
    }));
    const signature = btoa('mock-signature-for-ui-testing'); 
    return `${header}.${payload}.${signature}`;
  };

  const [qrValue, setQrValue] = useState(getSimulatedJwsToken());

  useEffect(() => {
        setQrValue(getSimulatedJwsToken());
        const interval = setInterval(() => {
            setQrValue(getSimulatedJwsToken());
        }, 30000); 

        return () => clearInterval(interval);
  }, [user]);


  if (!user) {
    return null;
  }

  return (
        <div className={cn(
            "bg-gradient-to-br p-6 rounded-lg text-primary-foreground shadow-2xl relative overflow-hidden",
            getPassGradient(user.role)
        )}>
            <div className="absolute top-2 right-2 flex items-center gap-1 text-xs bg-black/20 px-2 py-1 rounded-full">
               {os === 'iOS' ? <Apple className="h-4 w-4" /> : <Smartphone className="h-4 w-4" />}
               <span>{os} Wallet</span>
            </div>

            <div className="flex items-center gap-4">
                <Avatar className="h-16 w-16 border-2 border-white/50">
                    <AvatarImage src={`https://picsum.photos/100?q=${user.uid}`} alt={user.name} data-ai-hint="person face" />
                    <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                </Avatar>
                <div>
                    <p className="text-muted-foreground text-sm">{user.role}</p>
                    <h3 className="font-bold text-xl">{user.displayName}</h3>
                </div>
            </div>

            <div className="mt-6 p-4 bg-white rounded-md flex justify-center">
                 <QRCode value={qrValue} size={160} fgColor={qrColor} bgColor="#FFFFFF" />
            </div>

             <div className="text-center mt-4 text-xs text-white/80">
                <p>This QR code rotates periodically for security.</p>
            </div>
            
             <Badge className="absolute bottom-4 left-1/2 -translate-x-1/2 bg-green-500">
                Active
            </Badge>
        </div>
  );
}

    