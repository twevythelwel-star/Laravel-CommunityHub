
'use client';

import { useState, useEffect } from 'react';
import QRCode from "react-qr-code";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import type { Staff } from '@/types';
import { Avatar, AvatarFallback, AvatarImage } from '../ui/avatar';
import { Badge } from '../ui/badge';
import { Smartphone, Apple } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useIsClient } from '@/hooks/use-is-client';

type StaffGatePassDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  staff: Staff;
};

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

export function StaffGatePassDialog({ open, onOpenChange, staff }: StaffGatePassDialogProps) {
  const isClient = useIsClient();
  const [os, setOs] = useState<'iOS' | 'Android' | 'Other'>('Other');
  const [qrColor, setQrColor] = useState(highContrastColors[0]);
  
  useEffect(() => {
    if (isClient) {
        setOs(getOperatingSystem());
    }
  }, [isClient]);

   useEffect(() => {
    if (open) {
      // Pick a new random color from the list each time the dialog is opened
      const randomIndex = Math.floor(Math.random() * highContrastColors.length);
      setQrColor(highContrastColors[randomIndex]);
    }
  }, [open]);

  // This simulates the JWS token that would be returned from the `mintPass` cloud function.
  // A real implementation would call the backend function here to get a live token.
  const getSimulatedJwsToken = () => {
    const header = btoa(JSON.stringify({ alg: 'EdDSA', typ: 'JWT' }));
    const payload = btoa(JSON.stringify({
        sub: `staff:${staff.id}`,
        name: staff.name,
        role: "staff",
        iat: Math.floor(Date.now() / 1000),
        exp: Math.floor(Date.now() / 1000) + 60, // Expires in 60 seconds
    }));
    const signature = btoa('mock-signature-for-ui-testing'); // This is not a real signature
    return `${header}.${payload}.${signature}`;
  };

  const [qrValue, setQrValue] = useState(getSimulatedJwsToken());

  useEffect(() => {
    if (open) {
        // Set a new token when the dialog opens
        setQrValue(getSimulatedJwsToken());
        // Then, create an interval to rotate the token every 30 seconds
        const interval = setInterval(() => {
            setQrValue(getSimulatedJwsToken());
        }, 30000); // 30 seconds

        // Clear the interval when the dialog closes
        return () => clearInterval(interval);
    }
  }, [open, staff]);


  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>Digital Gate Pass</DialogTitle>
          <DialogDescription>
            Present this pass at the security gate for entry. This code rotates periodically.
          </DialogDescription>
        </DialogHeader>
        
        <div className="bg-gradient-to-br from-primary/80 to-accent/80 p-6 rounded-lg text-primary-foreground shadow-2xl relative overflow-hidden">
            <div className="absolute top-2 right-2 flex items-center gap-1 text-xs bg-black/20 px-2 py-1 rounded-full">
               {os === 'iOS' ? <Apple className="h-4 w-4" /> : <Smartphone className="h-4 w-4" />}
               <span>{os} Wallet</span>
            </div>

            <div className="flex items-center gap-4">
                <Avatar className="h-16 w-16 border-2 border-white/50">
                    {staff.photoUrl && <AvatarImage src={staff.photoUrl} alt={staff.name} />}
                    <AvatarFallback>{staff.name.charAt(0)}</AvatarFallback>
                </Avatar>
                <div>
                    <p className="text-muted-foreground text-sm">{staff.job}</p>
                    <h3 className="font-bold text-xl">{staff.name}</h3>
                    <p className="text-sm">{staff.property}</p>
                </div>
            </div>

            <div className="mt-6 p-4 bg-white rounded-md flex justify-center">
                 <QRCode value={qrValue} size={160} fgColor={qrColor} bgColor="#FFFFFF" />
            </div>

             <div className="text-center mt-4 text-xs text-white/80">
                <p>Tap QR code to activate NFC</p>
            </div>
            
             <Badge className={cn(
                "absolute bottom-4 left-1/2 -translate-x-1/2",
                staff.status === 'Active' ? 'bg-green-500' : 'bg-destructive'
             )}>
                {staff.status}
            </Badge>
        </div>
      </DialogContent>
    </Dialog>
  );
}
