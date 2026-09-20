import { 
  BoundaryPoint, 
  CommunityInfo, 
  BoundaryValidationResult, 
  BoundaryAuditLogEntry, 
  BoundaryConfig 
} from './types';
import { haversineDistance, toDMS } from '@/lib/geofence-utils';

export const COMMUNITY_INFO: CommunityInfo = {
  name: 'Cypress Bay Estate (Community A)',
  code: 'CID-CYPRESS-BAY',
  jurisdiction: 'St. James Parish, Jamaica',
  datum: 'WGS 84 / Jamaica National Grid (JNG)',
  referenceCoord: {
    lat: 18.476861,
    lng: -77.926028,
    dms: `18°28'36.7"N 77°55'33.7"W`,
    description: 'Primary Cadastral Benchmark (Gatehouse Command Axis)',
  },
  cadastralZone: 'Zone 01 - Primary Residential Enclave',
};

// Initial default boundary with user's supplied coordinate at Point 1
export const INITIAL_BOUNDARY_POINTS: BoundaryPoint[] = [
  {
    id: 1,
    label: 'Point 1',
    lat: 18.476861,
    lng: -77.926028,
    isOptional: false,
  },
  {
    id: 2,
    label: 'Point 2',
    lat: 18.478350,
    lng: -77.927200,
    isOptional: false,
  },
  {
    id: 3,
    label: 'Point 3',
    lat: 18.479100,
    lng: -77.924800,
    isOptional: false,
  },
  {
    id: 4,
    label: 'Point 4',
    lat: 18.478400,
    lng: -77.923200,
    isOptional: false,
  },
  {
    id: 5,
    label: 'Point 5',
    lat: 18.475800,
    lng: -77.923500,
    isOptional: true,
  },
  {
    id: 6,
    label: 'Point 6',
    lat: 18.474900,
    lng: -77.925500,
    isOptional: true,
  },
];

export const INITIAL_AUDIT_LOG: BoundaryAuditLogEntry[] = [
  {
    id: 'audit-001',
    community: 'Cypress Bay Estate (Community A)',
    action: 'Boundary Published',
    changedBy: 'Alexander Wright',
    role: 'System Admin',
    previousVersion: 1,
    newVersion: 2,
    pointsCount: 4,
    timestamp: '2026-08-15 09:30:00 EST',
    published: true,
    notes: 'Initial community cadastral quadrangle baseline established.',
    areaAcres: '41.20',
    perimeterMeters: 1640,
  },
  {
    id: 'audit-002',
    community: 'Cypress Bay Estate (Community A)',
    action: 'Boundary Updated',
    changedBy: 'Alexander Wright',
    role: 'System Admin',
    previousVersion: 2,
    newVersion: 3,
    pointsCount: 6,
    timestamp: '2026-09-02 14:15:00 EST',
    published: true,
    notes: 'Incorporated north ridge utility servitude and Point 1 benchmark (18.476861, -77.926028).',
    areaAcres: '48.65',
    perimeterMeters: 1880,
  },
];

// Helper: Check if line segment AB intersects line segment CD
function doSegmentsIntersect(
  p1: [number, number],
  p2: [number, number],
  p3: [number, number],
  p4: [number, number]
): boolean {
  const ccw = (A: [number, number], B: [number, number], C: [number, number]) => {
    return (C[1] - A[1]) * (B[0] - A[0]) > (B[1] - A[1]) * (C[0] - A[0]);
  };
  return (
    ccw(p1, p3, p4) !== ccw(p2, p3, p4) &&
    ccw(p1, p2, p3) !== ccw(p1, p2, p4)
  );
}

// Validation function enforcing the 4-8 point rule, coordinate bounds, and geometry
export function validateBoundary(points: BoundaryPoint[]): BoundaryValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];
  const pointCount = points.length;

  // 1. Point Count Invariants (4-8 points)
  if (pointCount < 4) {
    errors.push(`Requires 4–8 points (currently ${pointCount}). At least 4 points are needed to form a usable community polygon.`);
  } else if (pointCount > 8) {
    errors.push(`Maximum 8 points allowed (currently ${pointCount}). 9+ points cannot be added.`);
  }

  // 2. Coordinate range verification
  for (let i = 0; i < points.length; i++) {
    const pt = points[i];
    if (isNaN(pt.lat) || pt.lat < -90 || pt.lat > 90) {
      errors.push(`${pt.label}: Latitude must be between -90° and 90° (received ${pt.lat}).`);
    }
    if (isNaN(pt.lng) || pt.lng < -180 || pt.lng > 180) {
      errors.push(`${pt.label}: Longitude must be between -180° and 180° (received ${pt.lng}).`);
    }
  }

  // 3. Duplicate vertex check
  for (let i = 0; i < points.length; i++) {
    for (let j = i + 1; j < points.length; j++) {
      const dist = haversineDistance([points[i].lat, points[i].lng], [points[j].lat, points[j].lng]);
      if (dist < 2) {
        warnings.push(`${points[i].label} and ${points[j].label} are within 2m of each other; may cause vertex collapse.`);
      }
    }
  }

  // 4. Self-intersection check (Simple polygon validation)
  if (pointCount >= 4 && errors.length === 0) {
    const coords: [number, number][] = points.map(p => [p.lat, p.lng]);
    const n = coords.length;
    let hasIntersection = false;

    for (let i = 0; i < n; i++) {
      const a1 = coords[i];
      const a2 = coords[(i + 1) % n];

      for (let j = i + 1; j < n; j++) {
        // Ignore adjacent segments and the wraparound closing segment
        if (Math.abs(i - j) <= 1 || (i === 0 && j === n - 1)) continue;

        const b1 = coords[j];
        const b2 = coords[(j + 1) % n];

        if (doSegmentsIntersect(a1, a2, b1, b2)) {
          hasIntersection = true;
          errors.push(`Boundary self-intersects between ${points[i].label}–${points[(i + 1) % n].label} and ${points[j].label}–${points[(j + 1) % n].label}. Boundary must form a non-crossing perimeter.`);
          break;
        }
      }
      if (hasIntersection) break;
    }
  }

  // 5. Area, Perimeter, and Centroid calculation
  let center: [number, number] = [18.476861, -77.926028];
  let areaAcres = '0.00';
  let areaHectares = '0.00';
  let areaSqMeters = 0;
  let perimeterMeters = 0;

  if (pointCount > 0) {
    const sumLat = points.reduce((sum, p) => sum + p.lat, 0);
    const sumLng = points.reduce((sum, p) => sum + p.lng, 0);
    center = [sumLat / pointCount, sumLng / pointCount];
  }

  if (pointCount >= 3) {
    let area = 0;
    const n = pointCount;
    for (let i = 0; i < n; i++) {
      const p1 = points[i];
      const p2 = points[(i + 1) % n];
      perimeterMeters += haversineDistance([p1.lat, p1.lng], [p2.lat, p2.lng]);

      const x1 = (p1.lng - center[1]) * 111320 * Math.cos((center[0] * Math.PI) / 180);
      const y1 = (p1.lat - center[0]) * 110574;
      const x2 = (p2.lng - center[1]) * 111320 * Math.cos((center[0] * Math.PI) / 180);
      const y2 = (p2.lat - center[0]) * 110574;
      area += (x1 * y2 - x2 * y1);
    }
    areaSqMeters = Math.abs(area) / 2;
    areaAcres = (areaSqMeters * 0.000247105).toFixed(2);
    areaHectares = (areaSqMeters * 0.0001).toFixed(2);
    perimeterMeters = Math.round(perimeterMeters);
  }

  const isValid = errors.length === 0;
  const canPublish = isValid && pointCount >= 4 && pointCount <= 8;

  return {
    isValid,
    canPublish,
    pointCount,
    errors,
    warnings,
    areaAcres,
    areaHectares,
    areaSqMeters: Math.round(areaSqMeters),
    perimeterMeters,
    center,
  };
}

// Storage helpers
const STORAGE_KEY = 'community-boundary-config-v2';

export function loadBoundaryConfig(): BoundaryConfig {
  if (typeof window === 'undefined') {
    return {
      version: 3,
      status: 'PUBLISHED',
      points: INITIAL_BOUNDARY_POINTS,
      publishedCoordinates: INITIAL_BOUNDARY_POINTS.map(p => [p.lat, p.lng]),
      lastPublishedAt: '2026-09-02 14:15:00 EST',
      lastPublishedBy: 'Alexander Wright (System Admin)',
      auditHistory: INITIAL_AUDIT_LOG,
    };
  }

  const stored = localStorage.getItem(STORAGE_KEY);
  if (stored) {
    try {
      return JSON.parse(stored);
    } catch {
      // Fallback
    }
  }

  const initialConfig: BoundaryConfig = {
    version: 3,
    status: 'PUBLISHED',
    points: INITIAL_BOUNDARY_POINTS,
    publishedCoordinates: INITIAL_BOUNDARY_POINTS.map(p => [p.lat, p.lng]),
    lastPublishedAt: '2026-09-02 14:15:00 EST',
    lastPublishedBy: 'Alexander Wright (System Admin)',
    auditHistory: INITIAL_AUDIT_LOG,
  };

  localStorage.setItem(STORAGE_KEY, JSON.stringify(initialConfig));
  return initialConfig;
}

export function saveBoundaryConfig(config: BoundaryConfig): void {
  if (typeof window !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(config));
  }
}

// ── Manual Coordinate Parsing Utilities ──

export function parseSingleDMS(dmsStr: string): number | null {
  if (!dmsStr) return null;
  const clean = dmsStr.trim().replace(/[°'"]/g, ' ').replace(/\s+/g, ' ');
  const dmsRegex = /^(-?\d+(?:\.\d+)?)\s*(?:(\d+(?:\.\d+)?))?\s*(?:(\d+(?:\.\d+)?))?\s*([NSEW])?$/i;
  const match = clean.match(dmsRegex);
  if (!match) return null;

  const degrees = parseFloat(match[1]);
  const minutes = match[2] ? parseFloat(match[2]) : 0;
  const seconds = match[3] ? parseFloat(match[3]) : 0;
  const dir = match[4] ? match[4].toUpperCase() : null;

  if (isNaN(degrees)) return null;

  let decimal = Math.abs(degrees) + (minutes / 60) + (seconds / 3600);
  if (degrees < 0 || dir === 'S' || dir === 'W') {
    decimal = -decimal;
  }
  return +decimal.toFixed(6);
}

export function parseCoordinateString(input: string): { lat: number; lng: number } | null {
  if (!input || !input.trim()) return null;
  let trimmed = input.trim();

  // Strip Google Maps URL prefix or @ prefix if present e.g. "https://www.google.com/maps/@18.44976,-77.9144841,15.96z" or "@18.44976,-77.9144841"
  if (trimmed.includes('@')) {
    const atIdx = trimmed.indexOf('@');
    trimmed = trimmed.substring(atIdx + 1);
  }

  // Remove trailing zoom parameter like ",15.96z" or "/data=..." if present
  trimmed = trimmed.replace(/,\s*\d+(?:\.\d+)?z.*$/i, '');

  // Try standard comma-separated decimals e.g. "18.476861, -77.926028" or "18.476861,-77.926028"
  if (trimmed.includes(',')) {
    const parts = trimmed.split(',');
    if (parts.length >= 2) {
      const lat = parseFloat(parts[0].trim());
      const lng = parseFloat(parts[1].trim());
      if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        return { lat: +lat.toFixed(6), lng: +lng.toFixed(6) };
      }
    }
  }

  // Try space separated decimals e.g. "18.476861 -77.926028"
  const spaceParts = trimmed.split(/\s+/);
  if (spaceParts.length >= 2) {
    const lat = parseFloat(spaceParts[0]);
    const lng = parseFloat(spaceParts[1]);
    if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
      return { lat: +lat.toFixed(6), lng: +lng.toFixed(6) };
    }
  }

  // Try DMS e.g. "18°28'36.7\"N 77°55'33.7\"W" or "18°28'36.7\"N, 77°55'33.7\"W"
  const dmsMatch = trimmed.match(/(\d+°\d+['′][\d.]+["″]?\s*[NS])[,|\s]+(\d+°\d+['′][\d.]+["″]?\s*[EW])/i);
  if (dmsMatch) {
    const lat = parseSingleDMS(dmsMatch[1]);
    const lng = parseSingleDMS(dmsMatch[2]);
    if (lat !== null && lng !== null) {
      return { lat, lng };
    }
  }

  // Try generic pattern with N/S and E/W
  const nsMatch = trimmed.match(/([\d.]+\s*(?:°|deg)?\s*(?:[\d.]+\s*['m′]?)?\s*(?:[\d.]+\s*["s″]?)?\s*[NS])/i);
  const ewMatch = trimmed.match(/([\d.]+\s*(?:°|deg)?\s*(?:[\d.]+\s*['m′]?)?\s*(?:[\d.]+\s*["s″]?)?\s*[EW])/i);
  if (nsMatch && ewMatch) {
    const lat = parseSingleDMS(nsMatch[1]);
    const lng = parseSingleDMS(ewMatch[1]);
    if (lat !== null && lng !== null) {
      return { lat, lng };
    }
  }

  return null;
}

export function parseBulkCoordinates(text: string): { points: BoundaryPoint[]; errors: string[] } {
  const lines = text.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
  const points: BoundaryPoint[] = [];
  const errors: string[] = [];

  lines.forEach((line, index) => {
    // Strip prefixes like "Point 1:", "P1:", "1.", "1 -", etc.
    const cleanLine = line.replace(/^(?:Point\s*\d+|P\d+|\d+)\s*[:.-]\s*/i, '').trim();
    const parsed = parseCoordinateString(cleanLine);
    if (parsed) {
      if (points.length < 8) {
        const id = points.length + 1;
        points.push({
          id,
          label: `Point ${id}`,
          lat: parsed.lat,
          lng: parsed.lng,
          isOptional: id > 4,
        });
      }
    } else {
      errors.push(`Line ${index + 1} ("${line}"): Could not recognize coordinate format.`);
    }
  });

  return { points, errors };
}
