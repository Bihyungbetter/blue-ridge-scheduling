import CancelAppointmentDialog from '@/components/cancel-appointment-dialog';
import PageHeader from '@/components/page-header';
import { AppointmentStatusBadge, SourceBadge } from '@/components/scheduling-badges';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Appointment, type Option } from '@/types/scheduling';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appointments',
        href: '/appointments',
    },
];

interface Filters {
    date: string;
    department: string;
    status: string;
}

interface Props {
    appointments: Appointment[];
    filters: Filters;
    departments: Option[];
}

export default function AppointmentsIndex({ appointments, filters, departments }: Props) {
    const applyFilter = (changes: Partial<Filters>) => {
        router.get(route('appointments.index'), { ...filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appointments" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader eyebrow="Schedule" title="Appointments" />
                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="date">Date</Label>
                        <Input
                            id="date"
                            type="date"
                            className="w-44"
                            value={filters.date}
                            onChange={(e) => e.target.value && applyFilter({ date: e.target.value })}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>Department</Label>
                        <Select value={filters.department} onValueChange={(department) => applyFilter({ department })}>
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All departments</SelectItem>
                                {departments.map((d) => (
                                    <SelectItem key={d.value} value={d.value}>
                                        {d.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label>Status</Label>
                        <Select value={filters.status} onValueChange={(status) => applyFilter({ status })}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="scheduled">Scheduled</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                                <SelectItem value="all">All statuses</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <p className="eyebrow ml-auto pb-3">
                        {appointments.length} appointment{appointments.length === 1 ? '' : 's'}
                    </p>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-6">Time</TableHead>
                                    <TableHead>Patient</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Booked via</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="pr-6 text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {appointments.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-muted-foreground py-10 text-center">
                                            No appointments match these filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {appointments.map((a) => (
                                    <TableRow key={a.id}>
                                        <TableCell className="pl-6 tabular-nums">{a.time}</TableCell>
                                        <TableCell className="font-medium">{a.patient}</TableCell>
                                        <TableCell>{a.department}</TableCell>
                                        <TableCell>{a.type}</TableCell>
                                        <TableCell>
                                            <SourceBadge source={a.source} />
                                        </TableCell>
                                        <TableCell>
                                            <AppointmentStatusBadge status={a.status} />
                                        </TableCell>
                                        <TableCell className="pr-6 text-right">
                                            {a.status === 'scheduled' && <CancelAppointmentDialog appointment={a} />}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
