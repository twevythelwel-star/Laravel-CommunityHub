


export type UserRole = "System Admin" | "Admin" | "Homeowner" | "Temporary Homeowner" | "Security";

export type Homeowner = {
  id: string;
  name: string;
  lot: string;
  contact: string;
  active: boolean;
};

export type Renter = {
  id: string;
  name: string;
  status: 'Active' | 'Inactive';
  leaseStart: Date;
  leaseEnd: Date;
};

export type VisitorStatus = "Expected" | "Checked In" | "Checked Out";
export type EntryType = "onetime" | "recurring";

export type Visitor = {
  id: string;
  name: string;
  type: "One-time" | "Recurring";
  status: VisitorStatus;
  expectedAt: Date;
  dateRange: string;
  homeowner: string;
  idImageUrl?: string;
  isBlocked: boolean;
};

export type Notification = {
  id: string;
  title: string;
  content: string;
  timestamp: Date;
  author: string;
};

export type Warning = {
  id: string;
  title: string;
  description: string;
  timestamp: Date;
  author: string;
  confirms: number;
  denies: number;
  userStatus: 'confirmed' | 'denied' | null;
};

export type CommunityEvent = {
  id: string;
  title: string;
  date: Date;
  description: string;
};

export type Feedback = {
  id: string;
  submittedBy: string;
  userRole: UserRole;
  timestamp: Date;
  type: 'Issue' | 'Suggestion';
  subject: string;
  status: 'New' | 'In Progress' | 'Resolved';
};

export type ActivityLogEntry = {
    id: string;
    timestamp: Date;
    action: string;
}

export type AdminUser = {
    id: string;
    name: string;
    email: string;
    status: 'Active' | 'Inactive';
    createdAt: Date;
    activity: ActivityLogEntry[];
}

export type ManagedUser = {
    id: string;
    name: string;
    email: string;
    role: UserRole;
    status: 'Active' | 'Inactive';
    createdAt: Date;
}

export type BlocklistEntry = {
    id: string;
    name: string;
    photoUrl: string | null;
    reason: string;
    dateAdded: Date;
    expiryDate: Date | null; // null for permanent
    addedBy: string;
}
