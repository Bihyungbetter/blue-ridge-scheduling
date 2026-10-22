import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Circle, Mic, PhoneCall, PhoneOff } from 'lucide-react';
import type { RetellWebClient } from 'retell-client-js-sdk';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Test AI agent', href: '/agent-test' }];

interface Props {
    setup: { apiKey: boolean; voiceAgent: boolean; phoneNumber: boolean; smsEnabled: boolean };
}

type CallStatus = 'idle' | 'connecting' | 'live' | 'ended' | 'error';

interface TranscriptLine {
    role: 'agent' | 'user';
    content: string;
}

function xsrfToken() {
    return decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

function BrowserCall({ ready }: { ready: boolean }) {
    const client = useRef<RetellWebClient | null>(null);
    const [status, setStatus] = useState<CallStatus>('idle');
    const [agentTalking, setAgentTalking] = useState(false);
    const [transcript, setTranscript] = useState<TranscriptLine[]>([]);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => () => client.current?.stopCall(), []);

    const start = async () => {
        setError(null);
        setTranscript([]);
        setStatus('connecting');

        const response = await fetch(route('agent-test.web-call'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        });
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            setStatus('error');
            setError(body.message ?? 'Could not start the call.');
            return;
        }

        // Loaded on demand so the SDK only ships to people who use this page.
        const { RetellWebClient } = await import('retell-client-js-sdk');
        const web = new RetellWebClient();
        client.current = web;

        web.on('call_started', () => setStatus('live'));
        web.on('call_ended', () => {
            setStatus('ended');
            setAgentTalking(false);
        });
        web.on('agent_start_talking', () => setAgentTalking(true));
        web.on('agent_stop_talking', () => setAgentTalking(false));
        web.on('update', (update: { transcript?: TranscriptLine[] }) => {
            if (Array.isArray(update?.transcript)) setTranscript(update.transcript.slice(-8));
        });
        web.on('error', (e: unknown) => {
            setStatus('error');
            setError(String(e));
            web.stopCall();
        });

        try {
            await web.startCall({
                accessToken: body.access_token,
                callId: body.call_id,
                transport: body.transport,
                url: body.url,
                iceServers: body.ice_servers,
            });
        } catch (e) {
            setStatus('error');
            setError(e instanceof Error ? e.message : 'Microphone or connection failed.');
        }
    };

    const stop = () => {
        client.current?.stopCall();
        setStatus('ended');
    };

    const active = status === 'connecting' || status === 'live';

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-xl">Talk to it in your browser</CardTitle>
                <CardDescription>Uses your microphone. No phone number or Retell ID check needed, so it's the quickest test.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex flex-wrap items-center gap-3">
                    {active ? (
                        <Button variant="outline" onClick={stop}>
                            <PhoneOff /> End call
                        </Button>
                    ) : (
                        <Button onClick={start} disabled={!ready}>
                            <Mic /> Start browser call
                        </Button>
                    )}
                    <span className="text-foreground inline-flex items-center gap-2 text-sm">
                        <span
                            className={cn(
                                'size-2.5 rounded-full',
                                status === 'live' ? 'bg-success' : status === 'connecting' ? 'bg-primary animate-pulse' : 'bg-muted-foreground/40',
                            )}
                        />
                        {status === 'live' ? (agentTalking ? 'Agent is speaking…' : 'Listening…') : status === 'connecting' ? 'Connecting…' : status === 'ended' ? 'Call ended' : 'Not connected'}
                    </span>
                </div>
                {!ready && <p className="text-muted-foreground text-sm">Needs an API key and a voice agent. See the setup checklist.</p>}
                <InputError message={error ?? undefined} />
                {transcript.length > 0 && (
                    <div className="bg-muted space-y-2 rounded-xl p-4 text-sm">
                        <p className="eyebrow">Live transcript</p>
                        {transcript.map((line, i) => (
                            <p key={i}>
                                <span className="text-heading font-semibold">{line.role === 'agent' ? 'Agent' : 'You'}:</span> {line.content}
                            </p>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function PhoneForm({
    field,
    route: routeName,
    title,
    description,
    button,
    disabledReason,
}: {
    field: 'phone' | 'text_phone';
    route: string;
    title: string;
    description: string;
    button: string;
    disabledReason?: string;
}) {
    const { data, setData, post, processing, errors } = useForm<Record<string, string>>({ [field]: '' });
    const errorKey = field === 'phone' ? 'call' : 'text';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route(routeName), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-xl">{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-2">
                        <Label htmlFor={field}>Your phone number</Label>
                        <Input
                            id={field}
                            type="tel"
                            inputMode="tel"
                            autoComplete="tel-national"
                            placeholder="(828) 555-0100"
                            value={data[field]}
                            onChange={(e) => setData(field, e.target.value)}
                            disabled={!!disabledReason}
                        />
                        <InputError message={errors[field] ?? (errors as Record<string, string>)[errorKey]} />
                    </div>
                    <Button type="submit" variant="outline" disabled={processing || !!disabledReason}>
                        <PhoneCall /> {button}
                    </Button>
                    {disabledReason && <p className="text-muted-foreground text-sm">{disabledReason}</p>}
                    <p className="text-muted-foreground text-xs">Only use numbers that agreed to it, such as your own. AI calls to patients need their prior consent.</p>
                </form>
            </CardContent>
        </Card>
    );
}

function Check({ done, children }: { done: boolean; children: React.ReactNode }) {
    return (
        <li className="flex gap-3">
            {done ? <CheckCircle2 className="text-success mt-0.5 size-5 shrink-0" /> : <Circle className="text-muted-foreground mt-0.5 size-5 shrink-0" />}
            <div className="text-sm">{children}</div>
        </li>
    );
}

export default function AgentTest({ setup }: Props) {
    const { flash } = usePage<SharedData>().props;
    const voiceReady = setup.apiKey && setup.voiceAgent;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Test AI agent" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader eyebrow="Retell AI agent" title="Test the AI agent" />

                {flash?.status && (
                    <Alert>
                        <CheckCircle2 className="text-success size-4" />
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 xl:grid-cols-2">
                    <BrowserCall ready={voiceReady} />

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-xl">Setup checklist</CardTitle>
                            <CardDescription>What Retell needs before it can reach a phone.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-3">
                                <Check done={setup.apiKey}>
                                    <span className="font-medium">Retell API key</span> in <code>.env</code> as <code>RETELL_API_KEY</code>
                                </Check>
                                <Check done={setup.voiceAgent}>
                                    <span className="font-medium">Agents created:</span> run{' '}
                                    <code>php artisan retell:setup --url=https://&lt;tunnel&gt; --write-env</code>
                                </Check>
                                <Check done={setup.phoneNumber}>
                                    <span className="font-medium">Phone number</span> bought in Retell ($2/mo), set as <code>RETELL_FROM_NUMBER</code>, then
                                    re-run setup. Retell also requires its ID check (KYC) before outbound calls.
                                </Check>
                                <Check done={setup.smsEnabled}>
                                    <span className="font-medium">Texting approved:</span> US carriers require A2P 10DLC registration (about 2–3 weeks),
                                    then set <code>RETELL_SMS_ENABLED=true</code>.
                                </Check>
                            </ul>
                        </CardContent>
                    </Card>

                    <PhoneForm
                        field="phone"
                        route="agent-test.call"
                        title="Call a phone"
                        description="The agent calls the number and starts the scheduling conversation."
                        button="Call this number"
                        disabledReason={!voiceReady || !setup.phoneNumber ? 'Finish the API key, agent and phone number steps first.' : undefined}
                    />
                    <PhoneForm
                        field="text_phone"
                        route="agent-test.text"
                        title="Text a phone"
                        description="The SMS agent sends the first message; reply to keep chatting."
                        button="Text this number"
                        disabledReason={!setup.smsEnabled ? 'Available once texting is approved (see checklist).' : undefined}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
