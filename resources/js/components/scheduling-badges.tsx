import { Badge } from '@/components/ui/badge';
import { type AppointmentStatus, type BookingSource, type Conversation } from '@/types/scheduling';
import { MessageSquare, Phone, UserRound } from 'lucide-react';

const statusVariant: Record<AppointmentStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    scheduled: 'default',
    completed: 'secondary',
    cancelled: 'destructive',
    no_show: 'outline',
};

export function AppointmentStatusBadge({ status }: { status: AppointmentStatus }) {
    return <Badge variant={statusVariant[status]}>{status.replace('_', ' ')}</Badge>;
}

export function SourceBadge({ source }: { source: BookingSource }) {
    const Icon = source === 'voice' ? Phone : source === 'sms' ? MessageSquare : UserRound;
    const label = source === 'voice' ? 'AI call' : source === 'sms' ? 'AI text' : 'Staff';

    return (
        <Badge variant="outline" className="gap-1 font-normal">
            <Icon className="size-3" />
            {label}
        </Badge>
    );
}

export function OutcomeBadge({ conversation }: { conversation: Conversation }) {
    if (conversation.transferred) {
        return <Badge variant="secondary">Transferred</Badge>;
    }

    if (conversation.successful === null) {
        return <Badge variant="outline">{conversation.status.replace(/_/g, ' ')}</Badge>;
    }

    return conversation.successful ? <Badge>Resolved</Badge> : <Badge variant="destructive">Unresolved</Badge>;
}
