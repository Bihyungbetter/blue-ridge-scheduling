import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { type Appointment } from '@/types/scheduling';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function CancelAppointmentDialog({ appointment }: { appointment: Appointment }) {
    const [open, setOpen] = useState(false);
    const { post, processing, errors } = useForm({});

    const cancel: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('appointments.cancel', appointment.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm" className="text-destructive">
                    Cancel
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Cancel this appointment?</DialogTitle>
                <DialogDescription>
                    {appointment.patient} · {appointment.type} in {appointment.department} at {appointment.time}. The patient is not notified
                    automatically.
                </DialogDescription>
                <form onSubmit={cancel}>
                    <InputError message={(errors as Record<string, string>).appointment} />
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Keep appointment</Button>
                        </DialogClose>
                        <Button variant="destructive" disabled={processing} asChild>
                            <button type="submit">Cancel appointment</button>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
