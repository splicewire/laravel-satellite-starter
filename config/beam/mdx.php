<?php

return [
    // The starter renders Beam UX entries (ADR-0209), so no MDX file population is expected.
    // Any files later added under beam.mdx.content_path still receive every file-plane guard.
    'file_content_expected' => false,
];
