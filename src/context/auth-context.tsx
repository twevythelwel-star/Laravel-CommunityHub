
'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import type { User as FirebaseUser } from 'firebase/auth';
import { getAuth, onAuthStateChanged, signInWithEmailAndPassword, signOut } from 'firebase/auth';
import type { UserRole } from '@/types';
import { app } from '@/lib/firebase'; // Ensure firebase is initialized

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
  const auth = getAuth(app);

  useEffect(() => {
    const unsubscribe = onAuthStateChanged(auth, (firebaseUser: FirebaseUser | null) => {
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
  }, [auth]);

  const login = async (username: string, pass: string): Promise<boolean> => {
    setLoading(true);
    try {
      // Firebase auth expects an email format
      const email = `${username.toLowerCase()}@example.com`;
      await signInWithEmailAndPassword(auth, email, pass);
      // onAuthStateChanged will handle setting the user state
      return true;
    } catch (error) {
      console.error("Firebase login error:", error);
      setLoading(false);
      return false;
    }
  };

  const logout = async () => {
    try {
      await signOut(auth);
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
