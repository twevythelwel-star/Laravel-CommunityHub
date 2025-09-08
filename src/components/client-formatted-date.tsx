
'use client';

import { useState, useEffect } from 'react';
import { format, formatDistanceToNow } from 'date-fns';

type ClientFormattedDateProps = {
  date: Date;
  formatString: string;
};

export function ClientFormattedDate({ date, formatString }: ClientFormattedDateProps) {
  const [formattedDate, setFormattedDate] = useState('');

  useEffect(() => {
    setFormattedDate(format(date, formatString));
  }, [date, formatString]);

  if (!formattedDate) {
    return null; 
  }

  return <span>{formattedDate}</span>;
}


type ClientFormattedDistanceToNowProps = {
    date: Date;
};

export function ClientFormattedDistanceToNow({ date }: ClientFormattedDistanceToNowProps) {
    const [formattedDate, setFormattedDate] = useState('');
  
    useEffect(() => {
      setFormattedDate(formatDistanceToNow(new Date(date), { addSuffix: true }));
    }, [date]);
  
    if (!formattedDate) {
      return null;
    }
  
    return <span>{formattedDate}</span>;
  }
