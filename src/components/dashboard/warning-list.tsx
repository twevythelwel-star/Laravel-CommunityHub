
"use client";

import { useState, useEffect } from 'react';
import type { Warning } from '@/types';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { formatDistanceToNow } from 'date-fns';
import { ThumbsUp, ThumbsDown, UserCircle } from 'lucide-react';

type WarningListProps = {
  initialWarnings: Warning[];
};

export function WarningList({ initialWarnings }: WarningListProps) {
  const [warnings, setWarnings] = useState<Warning[]>(initialWarnings);
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  const handleVote = (id: string, vote: 'confirm' | 'deny') => {
    setWarnings(warnings.map(w => {
      if (w.id === id && w.userStatus === null) {
        return {
          ...w,
          confirms: vote === 'confirm' ? w.confirms + 1 : w.confirms,
          denies: vote === 'deny' ? w.denies + 1 : w.denies,
          userStatus: vote === 'confirm' ? 'confirmed' : 'denied',
        };
      }
      return w;
    }));
  };

  return (
    <div className="space-y-4">
      {warnings.map(warning => (
        <Card key={warning.id} className="shadow-sm hover:shadow-md transition-shadow">
          <CardHeader>
            <CardTitle>{warning.title}</CardTitle>
            <CardDescription className="flex items-center gap-2 pt-1 text-xs">
                <UserCircle className="h-4 w-4" /> 
                <span>{warning.author}</span> &middot; 
                <span>{isClient ? formatDistanceToNow(new Date(warning.timestamp), { addSuffix: true }) : '...'}</span>
            </CardDescription>
          </CardHeader>
          <CardContent>
            <p className="text-sm">{warning.description}</p>
          </CardContent>
          <CardFooter className="flex flex-wrap gap-4 justify-between items-center bg-muted/50 py-3 px-6">
            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                <div className="flex items-center gap-1.5">
                    <ThumbsUp className="h-4 w-4 text-green-600"/>
                    <span className="font-medium text-foreground">{warning.confirms}</span>
                    <span className="hidden sm:inline">Confirmed</span>
                </div>
                 <div className="flex items-center gap-1.5">
                    <ThumbsDown className="h-4 w-4 text-red-600"/>
                    <span className="font-medium text-foreground">{warning.denies}</span>
                     <span className="hidden sm:inline">Denied</span>
                </div>
            </div>
            <div className="flex items-center gap-2">
              <Button 
                variant={warning.userStatus === 'confirmed' ? 'secondary' : 'outline'}
                size="sm"
                onClick={() => handleVote(warning.id, 'confirm')}
                disabled={warning.userStatus !== null}
                className="bg-green-50 hover:bg-green-100 text-green-800 border-green-200 data-[disabled]:bg-slate-100 data-[disabled]:text-slate-500"
              >
                <ThumbsUp className="mr-2 h-4 w-4" />
                Confirm
              </Button>
              <Button 
                variant={warning.userStatus === 'denied' ? 'secondary' : 'outline'}
                size="sm"
                onClick={() => handleVote(warning.id, 'deny')}
                disabled={warning.userStatus !== null}
                 className="bg-red-50 hover:bg-red-100 text-red-800 border-red-200 data-[disabled]:bg-slate-100 data-[disabled]:text-slate-500"
              >
                <ThumbsDown className="mr-2 h-4 w-4" />
                Deny
              </Button>
            </div>
          </CardFooter>
        </Card>
      ))}
    </div>
  );
}

