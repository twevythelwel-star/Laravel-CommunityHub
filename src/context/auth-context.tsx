
'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import type { UserRole } from '@/types';

type User = {
  email: string;
  role: UserRole;
  uid: string;
};

type AuthContextType = {
  user: User | null;
  login: (username: string, pass: string) => Promise<boolean>;
  logout: () => void;
  loading: boolean;
};

// This is a mock mapping from email to role.
// In a real application, this would be stored in a database (e.g., Firestore).
const mockRoleMapping: Record<string, UserRole> = {
    'user-sysadmin@example.com': 'System Admin',
    'user-admin@example.com': 'Admin',
    'user-homeowner@example.com': 'Homeowner',
    'user-renter@example.com': 'Temporary Homeowner',
    'user-security@example.com': 'Security',
};


const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    // In this simplified setup, we don't need onAuthStateChanged
    // because we are not using real Firebase auth sessions.
    // We just set loading to false.
    setLoading(false);
  }, []);

  const login = async (username: string, pass: string): Promise<boolean> => {
    const email = `${username.toLowerCase()}@example.com`;
    const role = mockRoleMapping[email];

    if (role) {
      // Create a mock user object without calling Firebase
      const mockUser: User = {
        email: email,
        role: role,
        uid: `mock-uid-${username}`,
      };
      setUser(mockUser);
      return true;
    }

    return false;
  };

  const logout = async () => {
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
