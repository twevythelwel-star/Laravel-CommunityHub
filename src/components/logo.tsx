
'use client';

import { Building2 } from 'lucide-react';
import { useBranding } from '@/context/branding-context';
import Image from 'next/image';

export function Logo() {
    const { branding } = useBranding();

  return (
    <div className="flex items-center gap-2">
        {branding.iconUrl ? (
            <Image 
                src={branding.iconUrl} 
                alt={`${branding.communityName} Logo`}
                width={branding.iconSize}
                height={branding.iconSize}
                className="object-contain"
                style={{ width: `${branding.iconSize}px`, height: `${branding.iconSize}px` }}
            />
        ) : (
             <Building2 className="h-6 w-6 text-primary" />
        )}
      <h1 className="font-headline text-xl font-bold text-foreground">{branding.appName}</h1>
    </div>
  );
}
