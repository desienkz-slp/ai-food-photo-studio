<?php

// Set BUILD_RELEASE_VERSION from the tag used to build this release.
// Missing or invalid metadata disables update comparisons.
return [
    'version' => env('BUILD_RELEASE_VERSION'),
];
