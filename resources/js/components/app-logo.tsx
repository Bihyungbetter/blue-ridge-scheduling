import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 gap-1 text-left">
                <span className="text-heading truncate text-sm leading-none font-bold">Blue Ridge Scheduling</span>
                <span className="eyebrow truncate text-[0.625rem] leading-none">UNC Health Blue Ridge</span>
            </div>
        </>
    );
}
