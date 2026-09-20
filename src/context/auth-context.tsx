
'use client';

import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import type { UserRole } from '@/types';

export type User = {
  uid: string;
  name: string; // This is the "legal" name, not editable by user
  displayName: string;
  email: string;
  phone: string;
  role: UserRole;
  lot?: string;
  street?: string;
  title?: string;
  avatarUrl?: string;
};

type AuthContextType = {
  user: User | null;
  login: (username: string, pass: string) => Promise<boolean>;
  logout: () => void;
  updateUser: (updates: Partial<User>) => void;
  loading: boolean;
};

// Enterprise mock user database for Community Hub
const mockUserDatabase: Record<string, Omit<User, 'uid' | 'role'>> = {
    'user-sysadmin': { 
      name: 'Alexander Wright', 
      displayName: 'Alex Wright', 
      email: 'alexander.wright@communityhub.org', 
      phone: '(876) 555-0101',
      title: 'Senior Systems Administrator',
      lot: 'HQ-01',
      street: 'Executive Pavilion'
    },
    'user-admin': { 
      name: 'Elena Rostova', 
      displayName: 'Elena Rostova', 
      email: 'elena.rostova@communityhub.org', 
      phone: '(876) 555-0102',
      title: 'Community Operations Director',
      lot: 'Admin Suite',
      street: 'Central Clubhouse Way'
    },
    'user-homeowner': { 
      name: 'Marcus Vance', 
      displayName: 'Marcus Vance', 
      email: 'marcus.vance@residence.net', 
      phone: '(876) 555-0103',
      title: 'Verified Homeowner',
      lot: 'Lot 42',
      street: 'Royal Palm Drive'
    },
    'user-renter': { 
      name: 'Sophia Taylor', 
      displayName: 'Sophia Taylor', 
      email: 'sophia.taylor@residence.net', 
      phone: '(876) 555-0104',
      title: 'Resident Member',
      lot: 'Unit 15B',
      street: 'Hibiscus Crescent'
    },
    'user-security': { 
      name: 'Apex Security Command', 
      displayName: 'Security Dispatch', 
      email: 'dispatch@apexguard.com', 
      phone: '(876) 555-0105',
      title: 'Authorized Gate & Patrol Lead',
      lot: 'Gatehouse 1',
      street: 'Main Perimeter Entrance'
    },
    'user-staff': { 
      name: 'Maria Garcia', 
      displayName: 'Maria Garcia', 
      email: 'maria.garcia@communitystaff.org', 
      phone: '(876) 555-0106',
      title: 'Lead Facilities Coordinator',
      lot: 'Lot 42',
      street: 'Royal Palm Drive'
    },
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
      try {
        const parsed = JSON.parse(savedUser);
        const usernameKey = Object.keys(mockUserDatabase).find(k => 
          parsed.uid === `mock-uid-${k}` || 
          parsed.email === mockUserDatabase[k].email || 
          parsed.email === `${k}@example.com`
        );
        if (usernameKey && mockUserDatabase[usernameKey]) {
          const upgradedUser: User = {
            ...mockUserDatabase[usernameKey],
            ...parsed,
            name: mockUserDatabase[usernameKey].name,
            role: mockRoleMapping[usernameKey] || parsed.role,
            lot: parsed.lot || mockUserDatabase[usernameKey].lot,
            street: parsed.street || mockUserDatabase[usernameKey].street,
            title: parsed.title || mockUserDatabase[usernameKey].title,
            email: parsed.email?.includes('@example.com') ? mockUserDatabase[usernameKey].email : parsed.email,
          };
          setUser(upgradedUser);
          localStorage.setItem('currentUser', JSON.stringify(upgradedUser));
        } else {
          setUser(parsed);
        }
      } catch {
        const defaultUser: User = {
          uid: 'mock-uid-user-sysadmin',
          role: 'System Admin',
          ...mockUserDatabase['user-sysadmin']
        };
        setUser(defaultUser);
        localStorage.setItem('currentUser', JSON.stringify(defaultUser));
      }
    } else {
      const defaultUser: User = {
        uid: 'mock-uid-user-sysadmin',
        role: 'System Admin',
        ...mockUserDatabase['user-sysadmin']
      };
      setUser(defaultUser);
      localStorage.setItem('currentUser', JSON.stringify(defaultUser));
    }
    setLoading(false);
  }, []);

  const login = async (username: string, pass: string): Promise<boolean> => {
    // Basic password guard — even in mock mode, require a non-trivial password
    if (!pass || pass.length < 6) {
      return false;
    }

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
