<?php

namespace App\Plugins\NewsRadar;

use RuntimeException;

/**
 * Thrown when the Playwright package or its Chromium build is missing on the server.
 */
class PlaywrightNotConfiguredException extends RuntimeException {}
