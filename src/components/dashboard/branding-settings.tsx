
'use client';

import { useBranding } from '@/context/branding-context';
import { Label } from '../ui/label';
import { Input } from '../ui/input';
import { Button } from '../ui/button';
import { Slider } from '../ui/slider';
import { useToast } from '@/hooks/use-toast';
import { ChangeEvent } from 'react';

export function BrandingSettings() {
  const { branding, setBranding, resetBranding } = useBranding();
  const { toast } = useToast();

  const handleNameChange = (e: ChangeEvent<HTMLInputElement>) => {
    setBranding({ communityName: e.target.value });
  };

  const handleIconUpload = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 1024 * 1024) { // 1MB limit
        toast({
            variant: "destructive",
            title: "File Too Large",
            description: "Please upload an icon smaller than 1MB.",
        });
        return;
      }
      const reader = new FileReader();
      reader.onloadend = () => {
        setBranding({ iconUrl: reader.result as string });
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSizeChange = (value: number[]) => {
    setBranding({ iconSize: value[0] });
  };
  
  const handleReset = () => {
    resetBranding();
    toast({
        title: "Branding Reset",
        description: "Community name and icon have been reset to their defaults.",
    })
  }

  return (
    <div className="space-y-6">
      <div className="space-y-2">
        <Label htmlFor="community-name">Community Name</Label>
        <Input
          id="community-name"
          value={branding.communityName}
          onChange={handleNameChange}
        />
      </div>

      <div className="space-y-2">
        <Label htmlFor="icon-upload">Community Icon</Label>
        <Input 
          id="icon-upload" 
          type="file" 
          accept="image/png, image/jpeg, image/svg+xml, image/gif"
          onChange={handleIconUpload} 
        />
        <p className="text-xs text-muted-foreground">Upload a square image (PNG, JPG, SVG). Max 1MB.</p>
      </div>

      <div className="space-y-2">
        <Label>Icon Size</Label>
        <div className="flex items-center gap-4">
            <Slider
                value={[branding.iconSize]}
                onValueChange={handleSizeChange}
                min={24} // ~1 inch at 96dpi
                max={72} // ~3 inches at 96dpi, capped for layout sanity
                step={1}
            />
            <span className="text-sm text-muted-foreground w-12 text-right">{branding.iconSize}px</span>
        </div>
      </div>
      
      <Button onClick={handleReset} variant="outline">
        Reset to Defaults
      </Button>
    </div>
  );
}
