import { cn } from '@/lib/utils';
import { CalendarClock } from 'lucide-react';

// Neutral app mark. The UNC Health logo is a trademark: only swap it in with
// approved assets from UNC Health Blue Ridge marketing.
export default function AppLogoIcon({ className }: { className?: string }) {
    // Callers pass `fill-current` for the old filled SVG; an outline icon needs no fill.
    return <CalendarClock className={cn(className?.replace('fill-current', ''))} />;
}
