{{-- PAGE_START renders above .fi-page-header-main-ctn, the sibling that carries
     Filament's top padding-block; without a top gutter the callout sits flush
     against the topbar. Match Filament's own spacing rhythm (calc(--spacing * 8)
     = 2rem) so the banner lines up with the page header below it. --}}
<div style="padding-top: calc(var(--spacing, 0.25rem) * 8)">
    <x-filament::callout
        color="warning"
        icon="heroicon-o-exclamation-triangle"
        :heading="__('filament-password-rotation::messages.warning_heading')"
        :description="__('filament-password-rotation::messages.warning', ['date' => $expiresAt->toFormattedDateString()])"
    />
</div>
