
export type UserRole = "System Admin" | "Admin" | "Homeowner" | "Temporary Homeowner" | "Security" | "Staff";

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

export type Staff = {
  id: string;
  name: string;
  job: string;
  idType: "National ID" | "Driver's License" | "Passport";
  idNumber: string;
  idExpiry: Date;
  idImageUrl?: string;
  property: string; // e.g. "Lot 42, Main St"
  addedBy: string; // UID of homeowner/renter/admin
  status: 'Active' | 'Inactive' | 'Expired ID';
  photoUrl?: string;
}

export type Notification = {
  id: string;
  title: string;
  content: string;
  timestamp: Date;
  author: string;
};

export type Warning = {
  id:string;
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
  description: string;
  startDate: Date;
  endDate?: Date;
  imageUrl?: string;
};

export type CommunityUpdate = {
  id: string;
  title: string;
  date: Date;
  summary: string;
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
    lotNumber?: string;
    streetName?: string;
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

export type Business = {
  id: string;
  name: string;
  logoUrl: string;
  aiHint: string;
}

export type Voucher = {
  id: string;
  businessId: string;
  title: string;
  description: string;
}

export type FoodApp = {
  id: string;
  name: string;
  logoUrl: string;
  websiteUrl: string;
  aiHint: string;
  couponPercentage?: number;
}

export type Fundraiser = {
  id: string;
  title: string;
  description: string;
  goal: number;
  goalCurrency: 'JMD';
  startDate: Date;
  endDate: Date;
  status: 'Active' | 'Completed' | 'Upcoming' | 'Canceled';
}

export type Donation = {
  id: string;
  fundraiserId: string;
  amount: number;
  currency: 'JMD' | 'USD' | 'GBP' | 'EUR' | 'CAD';
  donorName?: string;
  isAnonymous: boolean;
  timestamp: Date;
}

export type Guideline = {
  id: string;
  category: string;
  title: string;
  description: string;
};
