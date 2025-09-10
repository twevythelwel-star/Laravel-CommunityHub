
import type { Visitor } from "@/types";
import { add, sub, format } from 'date-fns';

export const getInitialVisitors = (): Visitor[] => {
    const now = new Date();
    return [
        {
            id: "1",
            name: "Liam Johnson",
            type: "One-time",
            status: "Expected",
            expectedAt: now,
            dateRange: format(now, "yyyy-MM-dd"),
            homeowner: "Olivia Davis (Lot 42)",
            idImageUrl: "https://picsum.photos/300/200?q=id1",
            isBlocked: false,
        },
        {
            id: "2",
            name: "Noah Williams",
            type: "Recurring",
            status: "Checked In",
            expectedAt: sub(now, { days: 1 }),
            dateRange: `${format(sub(now, {days: 1}), "yyyy-MM-dd")} - ${format(add(now, {days: 60}), "yyyy-MM-dd")}`,
            homeowner: "John Smith (Lot 12)",
            idImageUrl: "https://picsum.photos/300/200?q=id2",
            isBlocked: false,
        },
        {
            id: "3",
            name: "Expired Visitor",
            type: "One-time",
            status: "Expected",
            expectedAt: sub(now, { hours: 13 }),
            dateRange: format(sub(now, { hours: 13 }), "yyyy-MM-dd"),
            homeowner: "John Smith (Lot 12)",
            isBlocked: false,
        },
         {
            id: "4",
            name: "Known Troublemaker",
            type: "One-time",
            status: "Expected",
            expectedAt: now,
            dateRange: format(now, "yyyy-MM-dd"),
            homeowner: "Jane Doe (Lot 03)",
            idImageUrl: "https://picsum.photos/300/200?q=trouble",
            isBlocked: true,
        },
        {
            id: "5",
            name: "Sophia Brown",
            type: "One-time",
            status: "Checked Out",
            expectedAt: sub(now, { days: 2 }),
            dateRange: format(sub(now, { days: 2 }), "yyyy-MM-dd"),
            homeowner: "Carlos Gomez",
            idImageUrl: "https://picsum.photos/300/200?q=id3",
            isBlocked: false,
        },
        {
            id: "6",
            name: "Suspicious Vehicle Owner",
            type: "One-time",
            status: "Expected",
            expectedAt: add(now, { days: 1 }),
            dateRange: format(add(now, { days: 1 }), "yyyy-MM-dd"),
            homeowner: "Aisha Khan",
            isBlocked: true,
        },
    ];
};
