import PageHeader from '@/components/page-header';
import { OutcomeBadge, SourceBadge } from '@/components/scheduling-badges';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Appointment, type Conversation } from '@/types/scheduling';
import { Head, Link } from '@inertiajs/react';
import { Bot, CalendarCheck, PhoneForwarded, PhoneIncoming } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface Props {
    stats: {
        appointmentsToday: number;
        aiBookedThisWeek: number;
        conversationsToday: number;
        transfersToday: number;
    };
    schedule: Appointment[];
    recentConversations: Conversation[];
}

export default function Dashboard({ stats, schedule, recentConversations }: Props) {
    const tiles = [
        { label: 'Appointments today', value: stats.appointmentsToday, icon: CalendarCheck },
        { label: 'AI bookings this week', value: stats.aiBookedThisWeek, icon: Bot },
        { label: 'AI conversations', value: stats.conversationsToday, icon: PhoneIncoming },
        { label: 'Transfers to staff', value: stats.transfersToday, icon: PhoneForwarded },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow={new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                    title="Scheduling overview"
                />
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {tiles.map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardDescription className="eyebrow">{label}</CardDescription>
                                <Icon className="text-primary size-5" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-heading text-4xl font-bold tabular-nums">{value}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-4 xl:grid-cols-5">
                    <Card className="xl:col-span-3">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <div className="space-y-1.5">
                                <CardTitle className="text-lg">Today's schedule</CardTitle>
                                <CardDescription>Active appointments across all departments</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('appointments.index')}>All appointments</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {schedule.length === 0 ? (
                                <p className="text-muted-foreground py-6 text-center text-sm">Nothing scheduled today.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Time</TableHead>
                                            <TableHead>Patient</TableHead>
                                            <TableHead>Department</TableHead>
                                            <TableHead>Booked via</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {schedule.map((a) => (
                                            <TableRow key={a.id}>
                                                <TableCell className="tabular-nums">{a.time}</TableCell>
                                                <TableCell className="font-medium">{a.patient}</TableCell>
                                                <TableCell>
                                                    {a.department}
                                                    <div className="text-muted-foreground text-xs">{a.type}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <SourceBadge source={a.source} />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="xl:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <div className="space-y-1.5">
                                <CardTitle className="text-lg">Recent AI conversations</CardTitle>
                                <CardDescription>Calls and texts handled by the Retell agent</CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('conversations.index')}>View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {recentConversations.length === 0 && (
                                <p className="text-muted-foreground py-6 text-center text-sm">No conversations yet.</p>
                            )}
                            {recentConversations.map((c) => (
                                <div key={c.id} className="space-y-1 border-b pb-3 last:border-0 last:pb-0">
                                    <div className="flex items-center justify-between gap-2 text-sm">
                                        <span className="font-medium">{c.patient ?? 'Unverified caller'}</span>
                                        <OutcomeBadge conversation={c} />
                                    </div>
                                    <p className="text-muted-foreground line-clamp-2 text-sm">{c.summary ?? 'Summary pending.'}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {c.channel === 'sms' ? 'Text' : 'Call'} · {c.at}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
