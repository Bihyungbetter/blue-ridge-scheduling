export type AppointmentStatus = 'scheduled' | 'cancelled' | 'completed' | 'no_show';

export type BookingSource = 'staff' | 'voice' | 'sms';

export interface Appointment {
    id: number;
    patient: string | null;
    department: string;
    type: string;
    starts_at: string;
    time: string;
    status: AppointmentStatus;
    source: BookingSource;
}

export interface Conversation {
    id: number;
    channel: 'voice' | 'sms';
    direction: 'inbound' | 'outbound' | null;
    patient: string | null;
    status: string;
    summary: string | null;
    sentiment: string | null;
    successful: boolean | null;
    transferred: boolean;
    disconnection_reason: string | null;
    duration_seconds: number | null;
    at: string;
}

export interface Option {
    value: string;
    label: string;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
