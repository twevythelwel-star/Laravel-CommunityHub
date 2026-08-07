
import { initializeApp, getApps, getApp, type FirebaseApp } from "firebase/app";
import { getAuth, type Auth } from "firebase/auth";
import { getFirestore, type Firestore } from "firebase/firestore";

const firebaseConfig = {
  apiKey: process.env.NEXT_PUBLIC_FIREBASE_API_KEY,
  authDomain: process.env.NEXT_PUBLIC_FIREBASE_AUTH_DOMAIN,
  projectId: process.env.NEXT_PUBLIC_FIREBASE_PROJECT_ID,
  storageBucket: process.env.NEXT_PUBLIC_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: process.env.NEXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID,
  appId: process.env.NEXT_PUBLIC_FIREBASE_APP_ID,
};

let app: FirebaseApp;
let auth: Auth;
let db: Firestore;

const isPlaceholder = (val?: string) =>
  !val || val === 'your-api-key-here' || val.startsWith('your-');

// Initialize Firebase robustly only on the client-side
if (typeof window !== 'undefined') {
  if (firebaseConfig.projectId && !isPlaceholder(firebaseConfig.apiKey)) {
    if (!getApps().length) {
      app = initializeApp(firebaseConfig);
    } else {
      app = getApp();
    }
    auth = getAuth(app);
    db = getFirestore(app);
  } else if (isPlaceholder(firebaseConfig.apiKey)) {
    console.warn(
      '[Community Hub] Firebase API key is a placeholder.\n' +
      'Get your real API key from: https://console.firebase.google.com\n' +
      '→ Project Settings → Your Apps → Web App → SDK config\n' +
      'Then update NEXT_PUBLIC_FIREBASE_API_KEY in .env.local\n\n' +
      'The app will continue to run using mock authentication — ' +
      'Firebase features (Firestore, Auth) will be disabled until a valid key is provided.'
    );
  } else {
    console.warn('[Community Hub] Firebase projectId is missing. Check your .env.local file.');
  }
}

export { app, auth, db };
