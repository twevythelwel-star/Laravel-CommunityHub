

import { Building2 } from 'lucide-react';
import { useBranding } from '@/context/branding-context';
import { Image } from '@/components/ui/image';

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
      <div className="flex flex-col min-w-0">
        <h1 className="font-headline text-base font-bold leading-tight text-foreground truncate">{branding.appName}</h1>
        {branding.communityName && (
          <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground truncate leading-tight">
            {branding.communityName}
          </span>
        )}
      </div>
    </div>
  );
}
