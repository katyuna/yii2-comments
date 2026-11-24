<?php

declare(strict_types=1);

namespace app\services;

use RuntimeException;

/**
 * Thrown when a real-time event could not be sent to Centrifugo.
 */
class RealtimePublishException extends RuntimeException
{
}
