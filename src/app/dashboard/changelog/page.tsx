
'use client';

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
import { ClientFormattedDate } from "@/components/client-formatted-date";
import { useAuth } from "@/context/auth-context";

type Change = {
    id: string;
    timestamp: Date;
    author: string;
    description: string;
    tags: string[];
}

const changelogData: Change[] = [
    {
        id: 'cl_6',
        timestamp: new Date('2024-08-01T15:00:00Z'),
        author: 'AI Assistant',
        description: 'Implemented dedicated pages for Account Deactivation and App Changelog with role-based access.',
        tags: ['Feature', 'Security', 'UI/UX'],
    },
    {
        id: 'cl_5',
        timestamp: new Date('2024-07-31T18:00:00Z'),
        author: 'AI Assistant',
        description: 'Enhanced the Visitor Management page for Security, allowing them to view/capture visitor IDs.',
        tags: ['Feature', 'Security'],
    },
    {
        id: 'cl_4',
        timestamp: new Date('2024-07-31T14:30:00Z'),
        author: 'AI Assistant',
        description: 'Overhauled the Directory page with role-based views and status management for System Admins and Admins.',
        tags: ['Feature', 'UI/UX', 'Admin'],
    },
    {
        id: 'cl_3',
        timestamp: new Date('2024-07-30T11:00:00Z'),
        author: 'AI Assistant',
        description: 'Added a dedicated, full-screen Community Map page, accessible to all user roles.',
        tags: ['Feature', 'Map'],
    },
     {
        id: 'cl_2',
        timestamp: new Date('2024-07-29T16:20:00Z'),
        author: 'AI Assistant',
        description: 'Enabled community branding customization (name and icon) for System Admins via the Settings page.',
        tags: ['Feature', 'Admin', 'UI/UX'],
    },
    {
        id: 'cl_1',
        timestamp: new Date('2024-07-29T10:00:00Z'),
        author: 'AI Assistant',
        description: 'Fixed persistent hydration errors and simplified Firebase/client-side initialization logic.',
        tags: ['Bugfix', 'Core'],
    },
];

export default function ChangelogPage() {
    const { user } = useAuth();

    if (user?.role !== 'System Admin') {
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
        <h1 className="font-headline text-3xl font-bold">App Changelog</h1>
        <p className="text-muted-foreground">A log of all major changes and feature additions to the application.</p>
      </div>
       <Card className="mt-8">
        <CardHeader>
          <CardTitle>Development History</CardTitle>
          <CardDescription>
            This log is automatically updated as the AI makes changes to the app.
          </CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Author</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Tags</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {changelogData.map((item) => (
                        <TableRow key={item.id}>
                             <TableCell>
                               <ClientFormattedDate date={item.timestamp} formatString="MMM d, yyyy" />
                            </TableCell>
                             <TableCell>
                                <Badge variant="secondary">{item.author}</Badge>
                            </TableCell>
                            <TableCell>{item.description}</TableCell>
                            <TableCell className="space-x-1">
                               {item.tags.map(tag => (
                                    <Badge key={tag} variant="outline">{tag}</Badge>
                               ))}
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
