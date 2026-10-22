<?php

// Only overrides the testing paths; everything else uses the package defaults.
// The starter kit keeps pages in resources/js/pages (lowercase), which matters
// on case-sensitive filesystems.

return [

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'ts', 'tsx'],
    ],

];
