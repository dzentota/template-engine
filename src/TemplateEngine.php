<?php

declare(strict_types=1);

namespace Dzentota\TemplateEngine;

use Dzentota\TemplateVariable\TemplateVariable;
use Dzentota\TemplateVariable\TemplateVariableCollection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Secure Template Engine with native PHP syntax
 * 
 * Features:
 * - Context-aware escaping using dzentota/template-variable
 * - Native PHP syntax similar to bareui
 * - Secure by default
 * - Extensible and configurable
 */
class TemplateEngine
{
    private array $config;
    private array $globalVariables = [];
    private array $paths = [];
    private ?string $cacheDir = null;
    private array $extensions = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'auto_escape' => true,
            'strict_variables' => true,
            'debug' => false,
            'cache' => false,
            'default_context' => 'html',
            'file_extension' => '.php'
        ], $config);

        if ($this->config['cache'] && isset($config['cache_dir'])) {
            $this->setCacheDirectory($config['cache_dir']);
        }
    }

    /**
     * Add a template directory path
     */
    public function addPath(string $path, string $namespace = '__main__'): self
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("Template directory does not exist: {$path}");
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $namespace)) {
            throw new InvalidArgumentException(
                "Invalid namespace '{$namespace}': must start with a letter or underscore and contain only alphanumeric characters and underscores."
            );
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            throw new InvalidArgumentException("Cannot resolve template directory path: {$path}");
        }

        $this->paths[$namespace] = $realPath;
        return $this;
    }

    /**
     * Set cache directory
     */
    public function setCacheDirectory(string $cacheDir): self
    {
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0700, true)) {
            throw new RuntimeException("Cannot create cache directory: {$cacheDir}");
        }

        if (!is_writable($cacheDir)) {
            throw new RuntimeException("Cache directory is not writable: {$cacheDir}");
        }

        $realCacheDir = realpath($cacheDir);
        if ($realCacheDir === false) {
            throw new RuntimeException("Cannot resolve cache directory path: {$cacheDir}");
        }

        $this->cacheDir = $realCacheDir;
        return $this;
    }

    /**
     * Add global variable available to all templates
     */
    public function addGlobal(string $name, mixed $value): self
    {
        $this->globalVariables[$name] = $value;
        return $this;
    }

    /**
     * Add multiple global variables
     */
    public function addGlobals(array $variables): self
    {
        $this->globalVariables = array_merge($this->globalVariables, $variables);
        return $this;
    }

    /**
     * Render a template with given variables
     */
    public function render(string $template, array $variables = []): string
    {
        $templateObj = $this->load($template);
        return $templateObj->render($variables);
    }

    /**
     * Load a template
     */
    public function load(string $name): Template
    {
        $path = $this->findTemplate($name);

        if ($this->cacheDir !== null) {
            $cacheKey = $this->getCacheKey($name, $path);
            if (!$this->isCacheValid($cacheKey, $path)) {
                $this->saveToCache($cacheKey, $path);
            }
        }

        return new Template($this, $path, $name);
    }

    /**
     * Create a secure template variable
     */
    public function createVariable(mixed $value, ?string $context = null): TemplateVariable|TemplateVariableCollection
    {
        return TemplateVariable::create($value);
    }

    /**
     * Get configuration value
     */
    public function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? null;
    }

    /**
     * Get global variables
     */
    public function getGlobals(): array
    {
        return $this->globalVariables;
    }

    /**
     * Find template file path
     */
    private function findTemplate(string $name): string
    {
        // Handle namespaced templates (e.g., "@namespace/template.php")
        if (str_starts_with($name, '@')) {
            [$namespace, $templateName] = explode('/', substr($name, 1), 2);
            
            if (!isset($this->paths[$namespace])) {
                throw new RuntimeException("Template namespace not found: {$namespace}");
            }
            
            $basePath = $this->paths[$namespace];
        } else {
            $basePath = $this->paths['__main__'] ?? '';
            $templateName = $name;
        }

        if (empty($basePath)) {
            throw new RuntimeException("No template directory configured");
        }

        // Ensure template name has proper extension
        if (!str_ends_with($templateName, $this->config['file_extension'])) {
            $templateName .= $this->config['file_extension'];
        }

        $fullPath = $basePath . '/' . $templateName;

        // Security: prevent directory traversal
        $realBasePath = realpath($basePath);
        $realTemplatePath = realpath($fullPath);

        if ($realTemplatePath === false || !str_starts_with($realTemplatePath, $realBasePath)) {
            throw new RuntimeException("Template not found or outside allowed directory: {$name}");
        }

        if (!is_file($realTemplatePath)) {
            throw new RuntimeException("Template file does not exist: {$name}");
        }

        return $realTemplatePath;
    }

    /**
     * Generate cache key for a template name + resolved path.
     * Uses SHA-256 over the realpath so the same file never gets duplicate entries.
     */
    private function getCacheKey(string $name, string $path): string
    {
        return hash('sha256', $name . '|' . $path);
    }

    /**
     * Return true when a valid, up-to-date cache entry exists for this key/path pair.
     */
    private function isCacheValid(string $cacheKey, string $originalPath): bool
    {
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';

        if (!is_file($cacheFile)) {
            return false;
        }

        $contents = file_get_contents($cacheFile);
        if ($contents === false) {
            return false;
        }

        $cacheData = json_decode($contents, true);

        if (!is_array($cacheData) || !isset($cacheData['path'], $cacheData['mtime'])) {
            // Corrupt cache entry — remove it
            @unlink($cacheFile);
            return false;
        }

        // Invalidate if the source file has been modified since the entry was written
        if (filemtime($originalPath) !== $cacheData['mtime']) {
            unlink($cacheFile);
            return false;
        }

        return true;
    }

    /**
     * Persist a resolved template path to the cache using an atomic write.
     */
    private function saveToCache(string $cacheKey, string $path): void
    {
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';
        // Write to a per-process temp file first, then rename (atomic on POSIX)
        $tmpFile = $cacheFile . '.' . getmypid() . '.tmp';

        $payload = json_encode([
            'path'  => $path,
            'mtime' => filemtime($path),
        ], JSON_THROW_ON_ERROR);

        file_put_contents($tmpFile, $payload, LOCK_EX);
        rename($tmpFile, $cacheFile);
    }
} 