import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { type AppointmentStatus, type BookingSource, type Conversation } from '@/types/scheduling';
import { MessageSquare, Phone, UserRound } from 'lucide-react';

const statusVariant: Record<AppointmentStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    scheduled: 'default',
    completed: 'secondary',
    cancelled: 'destructive',
    no_show: 'outline',
};

export function AppointmentStatusBadge({ status }: { status: AppointmentStatus }) {
    return (
        <Badge variant={statusVariant[status]} className="capitalize">
            {status.replace('_', ' ')}
        </Badge>
    );
}

export function SourceBadge({ source }: { source: BookingSource }) {
    const Icon = source === 'voice' ? Phone : source === 'sms' ? MessageSquare : UserRound;
    const label = source === 'voice' ? 'AI call' : source === 'sms' ? 'AI text' : 'Staff';

    return (
        <span className="text-foreground inline-flex items-center gap-1.5 text-sm">
            <Icon className="text-primary size-4" />
            {label}
        </span>
    );
}

// Dot + label, like "● Accepting New Patients" on UNC Health provider cards.
function DotLabel({ dotClass, children }: { dotClass: string; children: string }) {
    return (
        <span className="text-foreground inline-flex items-center gap-1.5 text-sm whitespace-nowrap">
            <span className={cn('size-2.5 rounded-full', dotClass)} />
            {children}
        </span>
    );
}

export function OutcomeBadge({ conversation }: { conversation: Conversation }) {
    if (conversation.transferred) {
        return <DotLabel dotClass="bg-primary">Transferred</DotLabel>;
    }

    if (conversation.successful === null) {
        return <DotLabel dotClass="bg-muted-foreground/50">{conversation.status.replace(/_/g, ' ')}</DotLabel>;
    }

    return conversation.successful ? <DotLabel dotClass="bg-success">Resolved</DotLabel> : <DotLabel dotClass="bg-destructive">Unresolved</DotLabel>;
}
