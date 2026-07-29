<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Override in .env (CORS_ALLOWED_ORIGINS) with comma-separated
    // origins in production. Includes local Vite dev server and the
    // Netlify deployment (moved here from allowed_origins_patterns —
    // it was a literal URL, not a regex, so it belonged here).
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173,https://astounding-blancmange-80c978.netlify.app'
    ))))),

    // Only use this for genuine regex matching (Netlify preview deploy
    // subdomains, Render preview URLs). Netlify's preview URL scheme is
    // `https://<branch>--<site-slug>.netlify.app`; this regex matches
    // all of them while staying tied to the configured production site
    // slug.
    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+--astounding-blancmange-80c978\.netlify\.app$#',
        '#^https://[a-z0-9-]+--[a-z0-9-]+\.onrender\.com$#',
    ],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    // The frontend currently uses a Bearer token in localStorage and
    // never sends cookies. Keep this false to avoid extra CORS surface
    // until the migration to httpOnly cookies lands.
    //
    // NOTE: 'sanctum/csrf-cookie' is still in `paths` above. That route
    // only matters for Sanctum's cookie-based SPA auth. If nothing in
    // this app actually calls it, you can remove it from `paths`. If
    // something *does* rely on it, supports_credentials must be `true`
    // and allowed_origins must NOT contain '*' for it to work.
    'supports_credentials' => false,

];
