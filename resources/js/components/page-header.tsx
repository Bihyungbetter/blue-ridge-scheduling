import { type ReactNode } from 'react';

// Eyebrow + bold navy title, the unchealth.org page-heading pattern.
export default function PageHeader({ eyebrow, title, children }: { eyebrow?: string; title: string; children?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="space-y-1">
                {eyebrow && <p className="eyebrow">{eyebrow}</p>}
                <h1 className="text-3xl font-bold md:text-4xl">{title}</h1>
            </div>
            {children}
        </div>
    );
}
