

import { useState, useEffect } from 'react';
import { format, formatDistanceToNow } from 'date-fns';

/**
 * `date` accepts what the implementation has always handled: it calls
 * `new Date(date)` internally, so an ISO string or epoch works. The type said
 * `Date` only because the mock data layer used Date objects — server payloads
 * are ISO strings, and every call site would otherwise need a cast.
 */
type DateLike = Date | string | number;

type ClientFormattedDateProps = {
  date: DateLike;
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
    date: DateLike;
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
