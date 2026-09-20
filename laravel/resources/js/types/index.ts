
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

/*
 * `ManagedUser` was removed with the directory wiring. Its `lotNumber` /
 * `streetName` fields never matched the server, which sends `lot` and `street`,
 * so keeping it around only invited the next page to adopt the wrong shape.
 * The directory's row type now lives beside its form, in
 * components/dashboard/create-user-form.tsx, as `DirectoryUser`.
 */

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

/*
 * `AccessLogEntry` was removed with the access-log wiring, for the same reason
 * as `ManagedUser`: it described something the server does not send. It had no
 * `result`, `denyReason` or `passId` — so the type itself was part of why the
 * page could not show a refused entry — typed `id` as a string, and closed
 * `gate` to three literals when the column is free text that the visitor API
 * will accept anything for. The row type now lives with its page, in
 * Pages/Dashboard/AccessLog.tsx, as `LogRow`.
 */
