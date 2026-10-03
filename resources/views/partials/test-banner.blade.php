@if(config('app.show_test_banner'))
{{-- Deliberately self-contained (own fixed positioning, own styles) rather than relying on
     each host page's layout — there are a dozen independent header implementations across
     this app (per-role layouts, both POS pages, standalone console pages), and a banner that
     has to be hand-fitted into each one's own flex/grid structure would be fragile. A fixed
     strip pinned above everything (including any page's own sticky header) plus a small body
     offset works identically no matter what the page underneath looks like. The only
     trade-off: on a page with its own sticky header, this strip sits in front of the top ~28px
     of it during scroll rather than stacking neatly below it — an acceptable cosmetic cost for
     "staff can never mistake this for production," which is the entire point of it existing. --}}
<div id="ssvk-test-banner" style="position:fixed; top:0; left:0; right:0; z-index:2147483647; height:28px; line-height:28px; background:#B91C1C; color:#fff; font:700 12px/28px -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; letter-spacing:0.04em; text-align:center; text-transform:uppercase; box-shadow:0 1px 4px rgba(0,0,0,0.35);">
    &#9888; Test Environment &mdash; Not Production Data &#9888;
</div>
<style>body{padding-top:28px !important;}</style>
@endif
