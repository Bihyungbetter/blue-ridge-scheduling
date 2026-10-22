# Muller (UNC Health's typeface)

UNC Health's websites use **Muller** by Fontfabric. It is a commercial font,
so its files are not included here. The free "Muller Narrow" cut is
non-commercial only and doesn't cover this app.

To use it:

1. Get a web license, either through UNC Health marketing (they may already
   hold one that covers internal tools) or from Fontfabric / MyFonts.
2. Put the licensed `.woff2` files here with these exact names:
   - `Muller-Light.woff2` (300)
   - `Muller-Regular.woff2` (400)
   - `Muller-Medium.woff2` (500)
   - `Muller-Bold.woff2` (700)
3. Reload. `resources/views/app.blade.php` registers each weight it finds,
   and the CSS font stack already puts Muller first.

Until then, the app falls back to Outfit (SIL OFL), a similar geometric sans.
Don't copy the webfont files from unchealth.org: UNC's license covers their
sites, not redistribution.
