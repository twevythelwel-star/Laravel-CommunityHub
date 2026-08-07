'use client';

import { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Sparkles } from 'lucide-react';
import Link from 'next/link';

const CONSENT_KEY = 'ai-feature-consent';

interface AIConsentDialogProps {
  onAccept: () => void;
  onDecline?: () => void;
}

export function AIConsentDialog({ onAccept, onDecline }: AIConsentDialogProps) {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const consented = localStorage.getItem(CONSENT_KEY);
    if (!consented) {
      setOpen(true);
    } else {
      onAccept();
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleAccept = () => {
    localStorage.setItem(CONSENT_KEY, 'true');
    setOpen(false);
    onAccept();
  };

  const handleDecline = () => {
    setOpen(false);
    onDecline?.();
  };

  return (
    <Dialog open={open} onOpenChange={() => {}}>
      <DialogContent className="sm:max-w-md" onInteractOutside={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-primary" />
            AI-Powered Feature
          </DialogTitle>
          <DialogDescription className="text-left space-y-2 pt-2">
            <span className="block">
              This feature uses <strong>Google Gemini</strong>, an external AI service operated by Google LLC,
              to process your content. The text you provide will be sent to Google&apos;s servers for AI processing.
            </span>
            <span className="block">
              By clicking <strong>I Understand</strong>, you consent to this data being sent to Google&apos;s AI
              service. Your consent is saved and you will not be asked again. See our{' '}
              <Link href="/privacy" className="underline text-primary" target="_blank">
                Privacy Policy
              </Link>{' '}
              for full details.
            </span>
          </DialogDescription>
        </DialogHeader>
        <DialogFooter className="flex-col sm:flex-row gap-2">
          <Button variant="outline" onClick={handleDecline} className="w-full sm:w-auto">
            Cancel
          </Button>
          <Button onClick={handleAccept} className="w-full sm:w-auto">
            I Understand, Continue
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
