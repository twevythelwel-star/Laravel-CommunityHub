'use client';

import React, { useState } from 'react';
import {
  Dialog,
  DialogContent,
} from '@/components/ui/dialog';
import type { Staff } from '@/types';
import type { PassCategory } from '@/lib/gate-pass-engine/types';
import { GatePassCard } from '@/components/dashboard/gate-pass-card';
import { GateScannerDialog } from '@/components/dashboard/gate-scanner-dialog';

type StaffGatePassDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  staff: Staff;
};

export function StaffGatePassDialog({ open, onOpenChange, staff }: StaffGatePassDialogProps) {
  const [scannerToken, setScannerToken] = useState<string | null>(null);

  // Classify: Homeowner domestic staff vs Community facility staff
  const isHomeownerDomestic = 
    staff.addedBy?.includes('homeowner') || 
    ['Housekeeper', 'Nanny', 'Private Gardener', 'Caregiver'].includes(staff.job) ||
    staff.property?.toLowerCase().includes('lot');

  const category: PassCategory = isHomeownerDomestic ? 'HOMEOWNER_STAFF' : 'STAFF';

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="sm:max-w-md p-0 overflow-hidden border-0 bg-transparent shadow-none">
          <GatePassCard
            category={category}
            userName={staff.name}
            property={staff.property}
            userId={staff.id}
            passId={`GP-${isHomeownerDomestic ? 'HST' : 'STF'}-${staff.id.replace(/\D/g, '').padStart(4, '0')}`}
            gate="GATE-01"
            photoUrl={staff.photoUrl}
            status={staff.status === 'Active' ? 'ACTIVE' : 'EXPIRED'}
            onScanInVerifier={(token) => {
              setScannerToken(token);
              onOpenChange(false);
            }}
          />
        </DialogContent>
      </Dialog>

      {/* Linked Gate Security Scanner Verification Console */}
      <GateScannerDialog
        open={!!scannerToken}
        onOpenChange={(isOpen) => !isOpen && setScannerToken(null)}
        initialToken={scannerToken || undefined}
      />
    </>
  );
}
