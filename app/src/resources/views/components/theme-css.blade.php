{{--
    The theme's design tokens as CSS custom properties — every layout's
    <head> includes this right after the compiled stylesheet, so the
    semantic utilities in app.css resolve. Values come from
    config/theme.php through App\Theme\DesignTokens.
--}}
<style>{!! App\Theme\DesignTokens::css() !!}</style>
