
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
  
  useEffect(() => {
    if (isClient) {
        setOs(getOperatingSystem());
    }
  }, [isClient]);

  const qrValue = JSON.stringify({
    staffId: staff.id,
    name: staff.name,
    property: staff.property,
    timestamp: new Date().toISOString(),
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>Digital Gate Pass</DialogTitle>
          <DialogDescription>
            Present this pass at the security gate for entry.
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
                 <QRCode value={qrValue} size={160} />
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
