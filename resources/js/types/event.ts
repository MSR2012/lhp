export interface EventImage {
    id: string;
    eventId: string;
    path: string;
    displayOrder: number;
}

export interface EventPayload {
    name: string;
    category: string;
    description: string;
    organizer: {
        name: string;
        verified: boolean;
    };
    venue: {
        name: string;
        capacity: string | number;
    };
    location: {
        lat: string | number;
        lng: string | number;
    };
    schedule: {
        starts_at: string;
        ends_at: string;
    };
    pricing: {
        currency: string;
        min_price: string | number;
    };
    tags: string[];
    notes: string;
}

export interface EventRow {
    id: string;
    userId: string;
    type: string;
    status: string;
    createdTime: number | null;
    latitude: number | null;
    longitude: number | null;
    address: string | null;
    timezone: string | null;
    payload: EventPayload;
    images: EventImage[];
    attendeesCount: number;
}
