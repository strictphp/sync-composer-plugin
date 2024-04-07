<?php

declare(strict_types=1);

namespace StrictPhp\SyncScriptsPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use RuntimeException;
use StrictPhp\SyncScriptsPlugin\Exceptions\DebugException;
use StrictPhp\SyncScriptsPlugin\Exceptions\FailedException;

final class ScriptsUpdaterPlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    public const KeyScripts = 'scripts';
    private const KeyPlugin = 'strictphp/sync-composer-plugin';
    private const KeyExtra = 'extra';
    private const KeyPluginSource = 'source';

    private ?Composer $composer = null;

    private ?IOInterface $io = null;

    public function getCapabilities(): array
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'post-update-cmd' => 'updateScriptsFromEvent',
        ];
    }

    public function updateScriptsFromEvent(Event $event): void
    {

        try {
            $this->updateScripts();
        } catch (DebugException $exception) {
            if (! $this->io instanceof IOInterface) {
                throw new RuntimeException('IO instance is not set.', $exception->getCode(), $exception);
            }

            $this->io->debug($exception->getMessage());
        } catch (FailedException $exception) {
            if (! $this->io instanceof IOInterface) {
                throw new RuntimeException('IO instance is not set.', $exception->getCode(), $exception);
            }

            $this->io->writeError($exception->getMessage());
        }
    }

    /**
     * Returns debug warning.
     */
    public function updateScripts(): bool
    {
        if (! $this->composer instanceof Composer) {
            throw new RuntimeException('Composer instance is not set.');
        }

        if (! $this->io instanceof IOInterface) {
            throw new RuntimeException('IO instance is not set.');
        }

        $composerJsonPath = 'composer.json';

        $composerJson = self::loadJson($composerJsonPath);

        $scriptsJsonRawPath = $this->getSource($composerJson);

        if ($scriptsJsonRawPath === null) {
            $scriptsJsonRawPath = 'composer-scripts.json';
        }

        $scriptsJsonPath = realpath($scriptsJsonRawPath);

        if ($scriptsJsonPath === false) {
            throw new DebugException('Source scripts does not exists at ' . $scriptsJsonRawPath);
        }

        $scriptsData = self::resolveScriptsFile($this->io, $scriptsJsonPath);

        if ($scriptsData === null) {
            throw new DebugException('Scripts file could not be resolved: ' . $scriptsJsonPath);
        }

        $originalScripts = array_key_exists(self::KeyScripts, $composerJson)
        && is_array($composerJson[self::KeyScripts])
            ? $composerJson[self::KeyScripts]
            : [];

        // To be able to update base scripts, we need to overwrite the original scripts.
        $mergedScripts = array_merge($originalScripts, $scriptsData[self::KeyScripts]);

        // To make the scripts clean, lets sort it.
        ksort($mergedScripts);

        $composerJson[self::KeyScripts] = $mergedScripts;

        // array_diff_assoc does not work on complicated changes (changing value of key from string to array)
        $backupChecksum = md5(self::encode($originalScripts));
        $proposed = md5(self::encode($composerJson[self::KeyScripts]));

        if ($backupChecksum === $proposed) {
            return false;
        }

        // Write the updated composer.json, add empty line at end as PHPStorm does.
        $finalComposerJson = self::encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($composerJsonPath, $finalComposerJson . PHP_EOL);

        $this->io->write('Scripts section updated');

        return true;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * @param array<string, string> $alreadyResolvedMap
     *
     * @return array{extends: string|null, scripts: array<string, string|string[]>}|null
     */
    private static function resolveScriptsFile(
        IOInterface $io,
        string $filePath,
        array $alreadyResolvedMap = []
    ): ?array {
        if (array_key_exists($filePath, $alreadyResolvedMap)) {
            $io->writeError('Circular reference detected in script extends. Skipping: ' . $filePath);
            return null;
        }

        $alreadyResolvedMap[$filePath] = $filePath;

        if (! file_exists($filePath)) {
            $io->writeError('Scripts file does not exist: ' . $filePath);
            return null;
        }

        $data = self::loadJson($filePath);

        if (! array_key_exists(self::KeyScripts, $data) || ! is_array($data[self::KeyScripts])) {
            $io->writeError('Scripts section not found in scripts file: ' . $filePath);
            return null;
        }

        if (array_key_exists('extends', $data) && is_string($data['extends'])) {
            $rawBasePath = dirname($filePath) . '/' . $data['extends'];
            $basePath = realpath($rawBasePath);

            if ($basePath === false) {
                $io->writeError('Base scripts file does not exist: ' . $rawBasePath);
                return null;
            }

            $io->debug('Resolving base scripts file: ' . $basePath);

            $baseData = self::resolveScriptsFile($io, $basePath, $alreadyResolvedMap);

            if ($baseData) {
                $data[self::KeyScripts] = array_merge($baseData[self::KeyScripts], $data[self::KeyScripts]);
            }
        } else {
            $data['extends'] = null;
            $io->debug('Extends not found in scripts file: ' . $filePath);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadJson(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new FailedException(('File does not exist: ' . $filePath));
        }

        $fileContents = file_get_contents($filePath);

        if ($fileContents === false) {
            throw new FailedException('Error reading ' . $filePath);
        }

        $result = json_decode($fileContents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new FailedException('Error decoding ' . $filePath . ': ' . json_last_error_msg());
        }

        if (! is_array($result)) {
            throw new FailedException('Invalid JSON in ' . $filePath);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function getSource(array $source): ?string
    {
        if (! array_key_exists(self::KeyExtra, $source)) {
            return null;
        }

        if (is_array($source[self::KeyExtra]) === false) {
            throw new FailedException('Extra must be an array');
        }

        if (! array_key_exists(self::KeyPlugin, $source[self::KeyExtra])) {
            return null;
        }

        if (is_array($source[self::KeyExtra][self::KeyPlugin]) === false) {
            throw new FailedException('Plugin options must be an array');
        }

        if (! array_key_exists(self::KeyPluginSource, $source[self::KeyExtra][self::KeyPlugin])) {
            return null;
        }

        $scriptsJsonRawPath = $source[self::KeyExtra][self::KeyPlugin][self::KeyPluginSource];

        if (! is_string($scriptsJsonRawPath)) {
            throw new FailedException('Source must be a string');
        }

        return $scriptsJsonRawPath;
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function encode(array $value, int $flags = 0): string
    {
        $json = json_encode($value, $flags);
        if ($json === false) {
            throw new FailedException('Error encoding JSON: ' . json_last_error_msg());
        }

        return $json;
    }
}
