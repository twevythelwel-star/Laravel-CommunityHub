export interface BoundaryPoint {
  id: number; // 1 to 8
  label: string; // e.g. "Point 1", "Point 2"
  lat: number;
  lng: number;
  isOptional: boolean; // false for 1..4, true for 5..8
}

export interface CommunityInfo {
  name: string;
  code: string;
  jurisdiction: string;
  datum: string;
  referenceCoord: {
    lat: number;
    lng: number;
    dms: string;
    description: string;
  };
  cadastralZone: string;
}

export interface BoundaryValidationResult {
  isValid: boolean;
  canPublish: boolean;
  pointCount: number;
  errors: string[];
  warnings: string[];
  areaAcres: string;
  areaHectares: string;
  areaSqMeters: number;
  perimeterMeters: number;
  center: [number, number];
}

export interface BoundaryAuditLogEntry {
  id: string;
  community: string;
  action: 'Boundary Published' | 'Boundary Updated' | 'Point Added' | 'Point Removed' | 'Draft Saved';
  changedBy: string;
  role: string;
  previousVersion: number;
  newVersion: number;
  pointsCount: number;
  timestamp: string;
  published: boolean;
  notes?: string;
  areaAcres: string;
  perimeterMeters: number;
}

export interface BoundaryConfig {
  version: number;
  status: 'DRAFT' | 'PUBLISHED';
  points: BoundaryPoint[];
  publishedCoordinates: [number, number][];
  lastPublishedAt?: string;
  lastPublishedBy?: string;
  auditHistory: BoundaryAuditLogEntry[];
}
