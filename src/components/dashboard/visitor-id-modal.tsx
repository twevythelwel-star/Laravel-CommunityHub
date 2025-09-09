
'use client';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Camera, FileQuestion } from 'lucide-react';
import Image from 'next/image';

type Visitor = {
  id: string;
  name: string;
  idImageUrl?: string;
};

type VisitorIdModalProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  visitor: Visitor;
};

export function VisitorIdModal({ open, onOpenChange, visitor }: VisitorIdModalProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Verify Visitor ID: {visitor.name}</DialogTitle>
          <DialogDescription>
            Confirm the visitor's identity using their provided ID.
          </DialogDescription>
        </DialogHeader>
        <div className="flex justify-center items-center p-4 my-4 rounded-lg bg-muted min-h-[200px]">
          {visitor.idImageUrl ? (
            <Image
              src={visitor.idImageUrl}
              alt={`ID for ${visitor.name}`}
              width={300}
              height={200}
              className="object-contain rounded-md"
              data-ai-hint="identification card"
            />
          ) : (
            <div className="text-center text-muted-foreground">
                <FileQuestion className="mx-auto h-12 w-12" />
                <p className="mt-2">No ID was uploaded by the homeowner.</p>
            </div>
          )}
        </div>
        <DialogFooter className="sm:justify-center">
            <Button type="button">
                <Camera className="mr-2 h-4 w-4" />
                Capture New ID
            </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
