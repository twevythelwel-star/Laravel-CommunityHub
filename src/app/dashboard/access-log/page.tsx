
'use client';

import { useState } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
  } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import type { AccessLogEntry, UserRole } from "@/types";
import { ClientFormattedDate } from "@/components/client-formatted-date";
import { useAuth } from "@/context/auth-context";

const mockAccessLog: AccessLogEntry[] = [
    { id: 'al_1', userName: 'Liam Johnson', userRole: 'Homeowner', method: 'Digital Pass', gate: 'Main Gate', timestamp: new Date('2024-07-30T10:00:00Z') },
    { id: 'al_2', userName: 'Guard McSecurity', userRole: 'Security', method: 'Digital Pass', gate: 'Main Gate', timestamp: new Date('2024-07-30T09:58:00Z') },
    { id: 'al_3', userName: 'Maria Garcia', userRole: 'Staff', method: 'Staff Pass', gate: 'Service Gate', timestamp: new Date('2024-07-30T09:45:00Z') },
    { id: 'al_4', userName: 'Noah Williams', userRole: 'Homeowner', method: 'Visitor Pass (for guest)', gate: 'Main Gate', timestamp: new Date('2024-07-30T09:30:00Z') },
    { id: 'al_5', userName: 'Sam Wilson', userRole: 'Temporary Homeowner', method: 'Digital Pass', gate: 'Main Gate', timestamp: new Date('2024-07-30T09:15:00Z') },
];


export default function AccessLogPage() {
    const { user } = useAuth();
    const [logEntries] = useState<AccessLogEntry[]>(mockAccessLog);

    const getRoleBadgeVariant = (role: UserRole) => {
        switch (role) {
            case 'System Admin': return 'destructive';
            case 'Admin': return 'destructive';
            case 'Homeowner': return 'default';
            case 'Temporary Homeowner': return 'secondary';
            case 'Security': return 'outline';
            case 'Staff': return 'outline';
            default: return 'secondary';
        }
    }

    if (user?.role !== 'Admin' && user?.role !== 'System Admin') {
        return (
             <Card>
                <CardHeader>
                    <CardTitle>Access Denied</CardTitle>
                </CardHeader>
                <CardContent>
                    <p>You do not have permission to view this page.</p>
                </CardContent>
            </Card>
        )
    }

  return (
    <div className="grid gap-8">
        <div>
            <h1 className="font-headline text-3xl font-bold">Access Log</h1>
            <p className="text-muted-foreground">A real-time log of all community entry and exit events.</p>
        </div>
       <Card>
        <CardHeader>
          <CardTitle>Recent Activity</CardTitle>
          <CardDescription>
            This log shows all access events recorded by the gate system.
          </CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>User</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead>Gate</TableHead>
                        <TableHead>Timestamp</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {logEntries.map((entry) => (
                        <TableRow key={entry.id}>
                            <TableCell className="font-medium">{entry.userName}</TableCell>
                            <TableCell>
                                <Badge variant={getRoleBadgeVariant(entry.userRole)}>{entry.userRole}</Badge>
                            </TableCell>
                            <TableCell>{entry.method}</TableCell>
                            <TableCell>{entry.gate}</TableCell>
                            <TableCell>
                                <ClientFormattedDate date={entry.timestamp} formatString="MMM d, yyyy, h:mm:ss a" />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </CardContent>
      </Card>
    </div>
  );
}
