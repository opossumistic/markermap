<?php

namespace App\Validation;

/**
 * Shared UGC field limits for map submissions and corrections.
 */
final class LocationFieldLimits
{
    /** Fits a mobile detail sheet without becoming a wall of text. */
    public const DESCRIPTION_MAX = 400;

    /** Internal moderation hint on corrections — short “why”, never published. */
    public const REASON_MAX = 200;

    /** Symfony Assert\File / Assert\Image maxSize (ingress before server-side normalize). */
    public const IMAGE_MAX_SIZE = '10M';

    /** Human-readable label for forms and validation messages. */
    public const IMAGE_MAX_SIZE_LABEL = '10 MB';

    private function __construct()
    {
    }
}
