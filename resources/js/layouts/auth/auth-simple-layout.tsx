import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

// White rounded panel on the UNC page wash, like the unchealth.org search panel.
export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 p-4 md:p-10">
            <div className="bg-card w-full max-w-md rounded-3xl p-8 md:p-10">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-3 font-medium">
                            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex size-12 items-center justify-center rounded-xl">
                                <AppLogoIcon className="size-7" />
                            </div>
                            <span className="eyebrow">Blue Ridge Scheduling</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-bold">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
