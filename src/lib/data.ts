
import type { BlocklistEntry } from "@/types";

const mockBlocklist: BlocklistEntry[] = [
    { 
        id: 'bl_1', 
        name: 'Known Troublemaker', 
        photoUrl: 'https://picsum.photos/100?q=trouble',
        reason: 'Repeatedly causing disturbances at community events. Multiple complaints filed.',
        dateAdded: new Date('2024-03-15T00:00:00Z'),
        addedBy: 'Admin',
        expiryDate: null,
    },
     { 
        id: 'bl_2', 
        name: 'Suspicious Vehicle Owner', 
        photoUrl: null,
        reason: 'Vehicle seen loitering at odd hours. License plate reported.',
        dateAdded: new Date('2024-07-10T00:00:00Z'),
        addedBy: 'Guard McSecurity',
        expiryDate: new Date('2025-01-10T00:00:00Z'),
    },
];

export function getBlocklist(): BlocklistEntry[] {
    // In a real application, this would fetch data from a database or API.
    return mockBlocklist;
}
