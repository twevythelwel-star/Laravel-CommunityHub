
'use client';

import { Logo } from "./logo";

const Gate = ({ side }: { side: 'left' | 'right' }) => (
  <svg
    className={`gate gate-${side}`}
    viewBox="0 0 200 400"
    xmlns="http://www.w3.org/2000/svg"
    preserveAspectRatio="none"
  >
    <rect width="200" height="400" fill="hsl(var(--card))" />
    <line x1="20" y1="0" x2="20" y2="400" stroke="hsl(var(--border))" strokeWidth="5" />
    <line x1="180" y1="0" x2="180" y2="400" stroke="hsl(var(--border))" strokeWidth="5" />

    {Array.from({ length: 9 }).map((_, i) => (
      <rect key={i} x="40" y={20 + i * 40} width="120" height="10" fill="hsl(var(--border))" rx="5" />
    ))}
    
    <circle cx="100" cy="200" r="30" fill="hsl(var(--card))" stroke="hsl(var(--border))" strokeWidth="5" />
     <path d="M 90 190 L 110 210 M 90 210 L 110 190" stroke="hsl(var(--border))" strokeWidth="3" />

  </svg>
);


export function WelcomeAnimation({ username }: { username: string }) {
  return (
    <div className="welcome-container">
      <Gate side="left" />
      <div className="welcome-message">
        <Logo />
        <h2 className="mt-4 text-3xl font-bold text-foreground">Welcome, {username}</h2>
      </div>
      <Gate side="right" />
    </div>
  );
}
