
'use client';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AdminUser } from '@/types';
import { ClientFormattedDistanceToNow } from '../client-formatted-date';

type AdminActivityLogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  admin: AdminUser;
};

export function AdminActivityLog({ open, onOpenChange, admin }: AdminActivityLogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>Activity Log: {admin.name}</DialogTitle>
          <DialogDescription>
            A log of recent actions performed by this administrator.
          </DialogDescription>
        </DialogHeader>
        <div className="max-h-[60vh] overflow-y-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Action</TableHead>
                        <TableHead>Timestamp</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {admin.activity.length > 0 ? (
                        admin.activity.map(log => (
                            <TableRow key={log.id}>
                                <TableCell className="font-medium">{log.action}</TableCell>
                                <TableCell>
                                    <ClientFormattedDistanceToNow date={log.timestamp} />
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell colSpan={2} className="text-center">
                                No activity recorded for this user.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
      </DialogContent>
    </Dialog>
  );
}
