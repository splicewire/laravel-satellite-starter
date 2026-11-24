<?php

/*
 * The brand is authored host configuration, not catalog copy.
 * Per localization 49 and root ruling 35b, a nonblank BEAM_BRAND_NAME wins; otherwise use a nonblank APP_NAME
 * unless it is Laravel; otherwise use this product's app.php default. The framework default name is treated as unset.
 * Resolve this during config loading so the result remains safe for config:cache.
 */
return [
    // The product brand after the BEAM_BRAND_NAME / APP_NAME precedence and Laravel-name guard above.
    'name' => (static function (): string {
        $brandName = env('BEAM_BRAND_NAME');
        if (is_string($brandName) && trim($brandName) !== '') {
            return $brandName;
        }

        $appName = env('APP_NAME');
        if (is_string($appName) && trim($appName) !== '' && $appName !== 'Laravel') {
            return $appName;
        }

        return 'Satellite';
    })(),
    // A URL or public path to the logo. Unset → none (renderers draw the name).
    'logo' => env('BEAM_BRAND_LOGO'),

    // Document title template, `:title` and `:name` placeholders. Unset → the renderer's own default.
    'title_template' => env('BEAM_BRAND_TITLE_TEMPLATE'),

    // The legal entity that processes end-user data on this install (the processor statement names it).
    // Unset → a neutral statement naming no company.
    'legal_entity' => env('BEAM_BRAND_LEGAL_ENTITY'),

    // The passkey card's sentence, verbatim. Unset → a neutral sentence built from `name`.
    'passkey_copy' => env('BEAM_BRAND_PASSKEY_COPY'),

    // How a buyer reaches the install (BUY-08, BQ-9): the upsell's "talk to us" address for a sales-led plan. Unset:
    // none, and the upsell offers no contact. A Splicewire host sets it to its one address (SPLICEWIRE_CONTACT_EMAIL).
    'contact' => [
        'sales' => env('BEAM_BRAND_CONTACT_SALES'),
    ],
];
