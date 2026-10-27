import PageHeader from '@/components/page-header';
import { OutcomeBadge } from '@/components/scheduling-badges';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Conversation, type Paginated } from '@/types/scheduling';
import { Head, Link, router } from '@inertiajs/react';
import { MessageSquare, Phone } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'AI conversations',
        href: '/conversations',
    },
];

interface Props {
    conversations: Paginated<Conversation>;
    filters: { channel: string };
}

function duration(seconds: number | null) {
    if (seconds === null) return '—';
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

export default function ConversationsIndex({ conversations, filters }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI conversations" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader eyebrow="Retell AI agent" title="AI conversations" />
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        className="gap-2"
                        value={filters.channel}
                        onValueChange={(channel) =>
                            channel && router.get(route('conversations.index'), { channel }, { preserveState: true, replace: true })
                        }
                    >
                        {[
                            ['all', 'All'],
                            ['voice', 'Calls'],
                            ['sms', 'Texts'],
                        ].map(([value, label]) => (
                            // UNC-style outlined pill chips
                            <ToggleGroupItem
                                key={value}
                                value={value}
                                className="border-primary text-primary data-[state=on]:bg-status data-[state=on]:text-status-foreground rounded-full! px-4"
                            >
                                {label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <p className="text-muted-foreground text-sm">Transcripts and recordings stay in Retell; only summaries are stored here.</p>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-6">When</TableHead>
                                    <TableHead>Channel</TableHead>
                                    <TableHead>Patient</TableHead>
                                    <TableHead className="w-1/2">Summary</TableHead>
                                    <TableHead>Length</TableHead>
                                    <TableHead className="pr-6">Outcome</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {conversations.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-muted-foreground py-10 text-center">
                                            No conversations yet. They appear here once Retell sends webhooks.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {conversations.data.map((c) => (
                                    <TableRow key={c.id}>
                                        <TableCell className="pl-6 whitespace-nowrap">{c.at}</TableCell>
                                        <TableCell>
                                            <span className="inline-flex items-center gap-1.5">
                                                {c.channel === 'sms' ? <MessageSquare className="size-4" /> : <Phone className="size-4" />}
                                                {c.channel === 'sms' ? 'Text' : 'Call'}
                                                {c.direction === 'outbound' && <span className="text-muted-foreground text-xs">(outbound)</span>}
                                            </span>
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {c.patient ?? <span className="text-muted-foreground">Unverified</span>}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground whitespace-normal">{c.summary ?? 'Summary pending.'}</TableCell>
                                        <TableCell className="tabular-nums">{duration(c.duration_seconds)}</TableCell>
                                        <TableCell className="pr-6">
                                            <OutcomeBadge conversation={c} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {conversations.last_page > 1 && (
                    <div className="flex items-center justify-end gap-2">
                        <span className="text-muted-foreground mr-2 text-sm">
                            Page {conversations.current_page} of {conversations.last_page}
                        </span>
                        <Button variant="outline" size="sm" disabled={!conversations.prev_page_url} asChild={!!conversations.prev_page_url}>
                            {conversations.prev_page_url ? <Link href={conversations.prev_page_url}>Previous</Link> : <span>Previous</span>}
                        </Button>
                        <Button variant="outline" size="sm" disabled={!conversations.next_page_url} asChild={!!conversations.next_page_url}>
                            {conversations.next_page_url ? <Link href={conversations.next_page_url}>Next</Link> : <span>Next</span>}
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
