'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import type { UserRole } from '@/types';

type User = {
  email: string;
  role: UserRole;
};

type AuthContextType = {
  user: User | null;
  login: (username: string, pass: string) => boolean;
  logout: () => void;
  loading: boolean;
};

const mockUsers: Record<string, { password: string, role: UserRole }> = {
    'User-Sysadmin': { password: 'password', role: 'System Admin' },
    'User-Admin': { password: 'password', role: 'Admin' },
    'User-Homeowner': { password: 'password', role: 'Homeowner' },
    'User-Renter': { password: 'password', role: 'Temporary Homeowner' },
    'User-Security': { password: 'password', role: 'Security' },
};

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    try {
      const storedUser = localStorage.getItem('user');
      if (storedUser) {
        setUser(JSON.parse(storedUser));
      }
    } catch (error) {
        console.error("Failed to parse user from localStorage", error);
        localStorage.removeItem('user');
    }
    setLoading(false);
  }, []);

  const login = (username: string, pass: string): boolean => {
    const mockUser = mockUsers[username];
    if (mockUser && mockUser.password === pass) {
      const userPayload = { email: `${username.toLowerCase()}@example.com`, role: mockUser.role };
      localStorage.setItem('user', JSON.stringify(userPayload));
      setUser(userPayload);
      return true;
    }
    return false;
  };

  const logout = () => {
    localStorage.removeItem('user');
    setUser(null);
    router.push('/');
  };

  return (
    <AuthContext.Provider value={{ user, login, logout, loading }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
