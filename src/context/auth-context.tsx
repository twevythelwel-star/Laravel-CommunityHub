
'use client';

import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import type { UserRole } from '@/types';

type User = {
  uid: string;
  name: string; // This is the "legal" name, not editable by user
  displayName: string;
  email: string;
  phone: string;
  role: UserRole;
};

type AuthContextType = {
  user: User | null;
  login: (username: string, pass: string) => Promise<boolean>;
  logout: () => void;
  updateUser: (updates: Partial<User>) => void;
  loading: boolean;
};

// This is a mock mapping from username to full user object.
// In a real application, this would be stored in a database (e.g., Firestore).
const mockUserDatabase: Record<string, Omit<User, 'uid' | 'role'>> = {
    'user-sysadmin': { name: 'Root Sysadmin', displayName: 'Root', email: 'user-sysadmin@example.com', phone: '555-0101' },
    'user-admin': { name: 'Lead Admin', displayName: 'LeadAdmin', email: 'user-admin@example.com', phone: '555-0102' },
    'user-homeowner': { name: 'Sample Homeowner', displayName: 'Homeowner', email: 'user-homeowner@example.com', phone: '555-0103' },
    'user-renter': { name: 'Sample Renter', displayName: 'Renter', email: 'user-renter@example.com', phone: '555-0104' },
    'user-security': { name: 'Community Security Inc.', displayName: 'Security', email: 'user-security@example.com', phone: '555-0105' },
    'user-staff': { name: 'Maria Garcia', displayName: 'Maria G.', email: 'maria.g@example.com', phone: '555-0106' },
};

const mockRoleMapping: Record<string, UserRole> = {
    'user-sysadmin': 'System Admin',
    'user-admin': 'Admin',
    'user-homeowner': 'Homeowner',
    'user-renter': 'Temporary Homeowner',
    'user-security': 'Security',
    'user-staff': 'Staff',
};


const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    // Check local storage for a logged-in user
    const savedUser = localStorage.getItem('currentUser');
    if (savedUser) {
        setUser(JSON.parse(savedUser));
    }
    setLoading(false);
  }, []);

  const login = async (username: string, pass: string): Promise<boolean> => {
    const role = mockRoleMapping[username.toLowerCase()];
    const userData = mockUserDatabase[username.toLowerCase()];

    if (role && userData) {
      const fullUser: User = {
        uid: `mock-uid-${username}`,
        role: role,
        ...userData
      };
      setUser(fullUser);
      localStorage.setItem('currentUser', JSON.stringify(fullUser));
      return true;
    }

    return false;
  };

  const logout = async () => {
    setUser(null);
    localStorage.removeItem('currentUser');
    router.push('/');
  };

  const updateUser = useCallback((updates: Partial<User>) => {
    setUser(currentUser => {
        if (currentUser) {
            const updatedUser = { ...currentUser, ...updates };
            localStorage.setItem('currentUser', JSON.stringify(updatedUser));
            return updatedUser;
        }
        return null;
    });
  }, []);


  return (
    <AuthContext.Provider value={{ user, login, logout, updateUser, loading }}>
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
