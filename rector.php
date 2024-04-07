<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use StrictPhp\Conventions\ExtensionFiles;

return RectorConfig::configure()
    ->withRootFiles()
    ->withPaths([__DIR__ . '/src'])
    ->withSets([ExtensionFiles::Rector]);
