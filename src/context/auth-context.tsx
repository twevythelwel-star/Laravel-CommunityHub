
'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import type { User as FirebaseUser } from 'firebase/auth';
import { onAuthStateChanged, signInWithEmailAndPassword, signOut } from 'firebase/auth';
import type { UserRole } from '@/types';
import { auth as firebaseAuth } from '@/lib/firebase'; 

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
// The default password for all mock users is "password".
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
    if (!firebaseAuth) {
      setLoading(false);
      return;
    }
    const unsubscribe = onAuthStateChanged(firebaseAuth, (firebaseUser: FirebaseUser | null) => {
      if (firebaseUser && firebaseUser.email) {
        // In a real app, you would fetch the user's role from your database (e.g., Firestore) here.
        // For now, we'll use the mock mapping.
        const role = mockRoleMapping[firebaseUser.email] || 'Homeowner'; 
        const userPayload: User = {
          email: firebaseUser.email,
          role: role,
          uid: firebaseUser.uid
        };
        setUser(userPayload);
      } else {
        setUser(null);
      }
      setLoading(false);
    });

    return () => unsubscribe();
  }, []);

  const login = async (username: string, pass: string): Promise<boolean> => {
    if (!firebaseAuth) {
      console.error("Firebase is not configured. Cannot log in.");
      return false;
    }
    try {
      // Firebase auth expects an email format
      const email = `${username.toLowerCase()}@example.com`;
      await signInWithEmailAndPassword(firebaseAuth, email, pass);
      // onAuthStateChanged will handle setting the user state
      return true;
    } catch (error) {
      console.error("Firebase login error:", error);
      return false;
    }
  };

  const logout = async () => {
     if (!firebaseAuth) {
      console.error("Firebase is not configured. Cannot log out.");
      return;
    }
    try {
      await signOut(firebaseAuth);
      setUser(null);
      router.push('/');
    } catch (error) {
      console.error("Firebase logout error:", error);
    }
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
