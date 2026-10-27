import UncHealthBlueRidgeLogo from './unc-logo';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            {/* Collapsed (icon-only) sidebar */}
            <div className="bg-sidebar-primary text-sidebar-primary-foreground hidden aspect-square size-8 items-center justify-center rounded-lg group-data-[collapsible=icon]:flex">
                <AppLogoIcon className="size-5" />
            </div>
            {/* Expanded sidebar */}
            {/* Wrapped: SidebarMenuButton shrinks direct <svg> children to icon size. */}
            <div className="w-14 shrink-0 group-data-[collapsible=icon]:hidden">
                <UncHealthBlueRidgeLogo className="w-full" />
            </div>
            <div className="border-sidebar-border grid flex-1 gap-1 border-l pl-3 text-left group-data-[collapsible=icon]:hidden">
                <span className="text-heading truncate text-base leading-none font-bold">Scheduling</span>
                <span className="eyebrow truncate text-[0.625rem] leading-none">Patient access</span>
            </div>
        </>
    );
}
