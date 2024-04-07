<?php

declare(strict_types=1);

namespace StrictPhp\SyncScriptsPlugin\Exceptions;

/**
 * Debug exceptions will be used for writing every message when using the script. In
 * events, it will be passed to io->debug() method.
 */
class DebugException extends FailedException
{
}
