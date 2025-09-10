
'use client';

import { useState, useEffect } from 'react';
import { format, formatDistanceToNow } from 'date-fns';

type ClientFormattedDateProps = {
  date: Date;
  formatString: string;
};

export function ClientFormattedDate({ date, formatString }: ClientFormattedDateProps) {
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  if (!isClient) {
    return null; 
  }

  return <span>{format(new Date(date), formatString)}</span>;
}


type ClientFormattedDistanceToNowProps = {
    date: Date;
};

export function ClientFormattedDistanceToNow({ date }: ClientFormattedDistanceToNowProps) {
    const [isClient, setIsClient] = useState(false);
  
    useEffect(() => {
      setIsClient(true);
    }, []);
  
    if (!isClient) {
      return null;
    }
  
    return <span>{formatDistanceToNow(new Date(date), { addSuffix: true })}</span>;
  }
