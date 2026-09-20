// ── Community Geofencing & Mapping Shared Utilities ──

export type LandmarkCategory = 'Security Gate' | 'Community Center' | 'Park';

export interface CommunityLandmark {
  id: string;
  name: string;
  category: LandmarkCategory;
  coordinates: [number, number];
  elevation: number; // in meters
  description: string;
  iconType: 'shield' | 'building' | 'trees';
  color: string; // Green for Security Gate (#10B981), Red for Community Center (#EF4444), Blue for Park (#3B82F6)
  isCustom?: boolean;
}

export const LANDMARK_PIN_CONFIGS: Record<LandmarkCategory, {
  color: string;
  label: string;
  badgeLabel: string;
  hexCode: string;
  iconType: 'shield' | 'building' | 'trees';
  presetNames: string[];
  descriptionPlaceholder: string;
}> = {
  'Security Gate': {
    color: '#10B981', // Green Pin
    label: 'Security Gate',
    badgeLabel: 'GREEN PIN',
    hexCode: '#10B981',
    iconType: 'shield',
    presetNames: [
      'Main Security Gate & Access Control',
      'North Perimeter Gate',
      'West Visitor Gate',
      'South Resident Egress Gate',
      'Service & Delivery Gate',
    ],
    descriptionPlaceholder: '24/7 security guardhouse, barrier gate, RFID scanner, and visitor intercom checkpoint.',
  },
  'Community Center': {
    color: '#EF4444', // Red Pin
    label: 'Community Center',
    badgeLabel: 'RED PIN',
    hexCode: '#EF4444',
    iconType: 'building',
    presetNames: [
      'Community Center & Clubhouse',
      'HOA Administration Office',
      'Resident Recreation Hall',
      'Community Event Pavilion',
      'Fitness & Wellness Center',
    ],
    descriptionPlaceholder: 'Central clubhouse complex, resident meeting lounge, and HOA administrative office.',
  },
  'Park': {
    color: '#3B82F6', // Blue Pin
    label: 'Park',
    badgeLabel: 'BLUE PIN',
    hexCode: '#3B82F6',
    iconType: 'trees',
    presetNames: [
      'Central Recreation Park',
      'Children Playground & Picnic Grounds',
      'Tennis & Sports Park',
      'Meadowview Nature Trail Park',
      'Dog Park & Exercise Field',
    ],
    descriptionPlaceholder: 'Landscaped recreational green space, outdoor seating, sports facilities, and children play zones.',
  },
};

export const DEFAULT_COMMUNITY_LANDMARKS: CommunityLandmark[] = [
  {
    id: 'gate-main',
    name: 'Main Security Gate & Access Control',
    category: 'Security Gate',
    coordinates: [18.4750, -77.9257],
    elevation: 42,
    description: 'Primary vehicular entry with 24/7 security guardhouse, barrier arms, and RFID pass scanners.',
    iconType: 'shield',
    color: '#10B981', // Green
  },
  {
    id: 'clubhouse',
    name: 'Community Center & Clubhouse',
    category: 'Community Center',
    coordinates: [18.4768, -77.9250],
    elevation: 58,
    description: 'Central community complex housing resident lounge, meeting room, and HOA management office.',
    iconType: 'building',
    color: '#EF4444', // Red
  },
  {
    id: 'rec-park',
    name: 'Central Recreation Park & Sports Courts',
    category: 'Park',
    coordinates: [18.4775, -77.9265],
    elevation: 65,
    description: 'Landscaped green space featuring tennis courts, children playground, and scenic jogging trails.',
    iconType: 'trees',
    color: '#3B82F6', // Blue
  },
  {
    id: 'gate-north',
    name: 'North Perimeter Security Gate',
    category: 'Security Gate',
    coordinates: [18.4782, -77.9258],
    elevation: 74,
    description: 'Automated emergency and resident-only egress gate with high-definition CCTV monitoring.',
    iconType: 'shield',
    color: '#10B981', // Green
  },
  {
    id: 'park-meadow',
    name: 'Meadowview Nature Park & Trail',
    category: 'Park',
    coordinates: [18.4760, -77.9238],
    elevation: 49,
    description: 'Scenic community walking trail, botanical flora garden, and shaded picnic gazebos.',
    iconType: 'trees',
    color: '#3B82F6', // Blue
  },
];

export const COMMUNITY_LANDMARKS: CommunityLandmark[] = DEFAULT_COMMUNITY_LANDMARKS;

const LANDMARKS_STORAGE_KEY = 'community-landmarks-v3';

export function loadCommunityLandmarks(): CommunityLandmark[] {
  if (typeof window === 'undefined') return DEFAULT_COMMUNITY_LANDMARKS;
  try {
    const stored = localStorage.getItem(LANDMARKS_STORAGE_KEY);
    if (stored) {
      const parsed = JSON.parse(stored);
      if (Array.isArray(parsed) && parsed.length > 0) {
        return parsed;
      }
    }
    localStorage.setItem(LANDMARKS_STORAGE_KEY, JSON.stringify(DEFAULT_COMMUNITY_LANDMARKS));
  } catch (e) {
    console.warn('Failed to load community landmarks from localStorage:', e);
  }
  return DEFAULT_COMMUNITY_LANDMARKS;
}

export function saveCommunityLandmarks(landmarks: CommunityLandmark[]): void {
  if (typeof window !== 'undefined') {
    try {
      localStorage.setItem(LANDMARKS_STORAGE_KEY, JSON.stringify(landmarks));
    } catch (e) {
      console.warn('Failed to save community landmarks:', e);
    }
  }
}


export const DEFAULT_GEOFENCE_COORDS: [number, number][] = [
  [18.4781, -77.9278],
  [18.4783, -77.9239],
  [18.4752, -77.9236],
  [18.4750, -77.9276],
];

export const GEOFENCE_PRESETS = [
  {
    name: 'Montego Palms Standard (Default)',
    description: 'Official surveyed community cadastral polygon (4 points)',
    coords: [
      [18.4781, -77.9278],
      [18.4783, -77.9239],
      [18.4752, -77.9236],
      [18.4750, -77.9276],
    ] as [number, number][],
  },
  {
    name: 'Extended Perimeter (+20% Buffer)',
    description: 'Includes outer greenway buffer and utility servitude (6 points)',
    coords: [
      [18.4788, -77.9284],
      [18.4791, -77.9233],
      [18.4760, -77.9228],
      [18.4744, -77.9230],
      [18.4742, -77.9282],
      [18.4765, -77.9286],
    ] as [number, number][],
  },
  {
    name: 'Central Residential Core',
    description: 'High-security inner residential enclave (4 points)',
    coords: [
      [18.4776, -77.9268],
      [18.4778, -77.9245],
      [18.4756, -77.9242],
      [18.4754, -77.9266],
    ] as [number, number][],
  },
];

// Helper to convert decimal degrees to DMS (Degrees Minutes Seconds)
export function toDMS(deg: number, isLat: boolean): string {
  const absolute = Math.abs(deg);
  const degrees = Math.floor(absolute);
  const minutesNotTruncated = (absolute - degrees) * 60;
  const minutes = Math.floor(minutesNotTruncated);
  const seconds = ((minutesNotTruncated - minutes) * 60).toFixed(1);
  const direction = isLat ? (deg >= 0 ? 'N' : 'S') : (deg >= 0 ? 'E' : 'W');
  return `${degrees}°${minutes}'${seconds}"${direction}`;
}

// Calculate approximate elevation based on Montego Bay coastal ridge interpolation
export function estimateElevation(lat: number, lng: number): number {
  const baseLat = 18.4750;
  const baseLng = -77.9270;
  const dLat = (lat - baseLat) * 111000;
  const dLng = (lng - baseLng) * 105000;
  const elev = Math.round(38 + (dLat * 0.08) + (dLng * 0.04));
  return Math.max(15, Math.min(180, elev));
}

// Haversine distance in meters
export function haversineDistance(c1: [number, number], c2: [number, number]): number {
  const R = 6371000;
  const dLat = ((c2[0] - c1[0]) * Math.PI) / 180;
  const dLng = ((c2[1] - c1[1]) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((c1[0] * Math.PI) / 180) *
      Math.cos((c2[0] * Math.PI) / 180) *
      Math.sin(dLng / 2) *
      Math.sin(dLng / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return Math.round(R * c);
}

// Ray-casting Point-in-Polygon
export function isPointInGeofence(point: [number, number], polygon: [number, number][]): boolean {
  if (polygon.length < 3) return false;
  const [lat, lng] = point;
  let inside = false;
  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
    const [latI, lngI] = polygon[i];
    const [latJ, lngJ] = polygon[j];
    const intersect = ((lngI > lng) !== (lngJ > lng)) && (lat < ((latJ - latI) * (lng - lngI)) / (lngJ - lngI) + latI);
    if (intersect) inside = !inside;
  }
  return inside;
}

// Distance to Geofence Perimeter & Closest Gate
export function getGeofencePerimeterInfo(point: [number, number], polygon: [number, number][]): {
  isInside: boolean;
  distanceMeters: number;
  nearestVertexIndex: number;
  nearestGate: { name: string; distance: number; coordinates: [number, number] };
} {
  const isInside = isPointInGeofence(point, polygon);
  let minDistance = Infinity;
  let nearestVertexIndex = 0;

  for (let i = 0; i < polygon.length; i++) {
    const dist = haversineDistance(point, polygon[i]);
    if (dist < minDistance) {
      minDistance = dist;
      nearestVertexIndex = i;
    }
  }

  const distMain = haversineDistance(point, [18.4750, -77.9257]);
  const distNorth = haversineDistance(point, [18.4782, -77.9258]);
  const nearestGate = distMain <= distNorth 
    ? { name: 'Main Security Gate', distance: distMain, coordinates: [18.4750, -77.9257] as [number, number] }
    : { name: 'North Perimeter Checkpoint', distance: distNorth, coordinates: [18.4782, -77.9258] as [number, number] };

  return {
    isInside,
    distanceMeters: minDistance,
    nearestVertexIndex,
    nearestGate,
  };
}

// Calculate polygon area, perimeter, and centroid
export function calculatePolygonMetrics(polygon: [number, number][]): {
  center: [number, number];
  areaAcres: string;
  areaHectares: string;
  perimeterMeters: number;
} {
  if (polygon.length === 0) {
    return {
      center: [18.4766, -77.9257],
      areaAcres: '0.00',
      areaHectares: '0.00',
      perimeterMeters: 0,
    };
  }

  const sumLat = polygon.reduce((sum, c) => sum + c[0], 0);
  const sumLng = polygon.reduce((sum, c) => sum + c[1], 0);
  const center: [number, number] = [sumLat / polygon.length, sumLng / polygon.length];

  if (polygon.length < 3) {
    return {
      center,
      areaAcres: '0.00',
      areaHectares: '0.00',
      perimeterMeters: 0,
    };
  }

  let area = 0;
  let perimeter = 0;
  const n = polygon.length;
  for (let i = 0; i < n; i++) {
    const p1 = polygon[i];
    const p2 = polygon[(i + 1) % n];
    perimeter += haversineDistance(p1, p2);
    const x1 = (p1[1] - center[1]) * 111320 * Math.cos((center[0] * Math.PI) / 180);
    const y1 = (p1[0] - center[0]) * 110574;
    const x2 = (p2[1] - center[1]) * 111320 * Math.cos((center[0] * Math.PI) / 180);
    const y2 = (p2[0] - center[0]) * 110574;
    area += (x1 * y2 - x2 * y1);
  }

  const sqMeters = Math.abs(area) / 2;
  return {
    center,
    areaAcres: (sqMeters * 0.000247105).toFixed(2),
    areaHectares: (sqMeters * 0.0001).toFixed(2),
    perimeterMeters: Math.round(perimeter),
  };
}
