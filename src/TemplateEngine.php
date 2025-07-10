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

        $this->paths[$namespace] = rtrim($path, '/');
        return $this;
    }

    /**
     * Set cache directory
     */
    public function setCacheDirectory(string $cacheDir): self
    {
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true)) {
            throw new RuntimeException("Cannot create cache directory: {$cacheDir}");
        }

        if (!is_writable($cacheDir)) {
            throw new RuntimeException("Cache directory is not writable: {$cacheDir}");
        }

        $this->cacheDir = $cacheDir;
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
        
        if ($this->config['cache'] && $this->cacheDir) {
            $cacheKey = $this->getCacheKey($name, $path);
            $cachedTemplate = $this->loadFromCache($cacheKey, $path);
            
            if ($cachedTemplate !== null) {
                return $cachedTemplate;
            }
        }

        $template = new Template($this, $path, $name);
        
        if ($this->config['cache'] && $this->cacheDir) {
            $this->saveToCache($cacheKey, $template);
        }

        return $template;
    }

    /**
     * Create a secure template variable
     */
    public function createVariable(mixed $value, string $context = null): TemplateVariable|TemplateVariableCollection
    {
        return TemplateVariable::create($value);
    }

    /**
     * Get configuration value
     */
    public function getConfig(string $key = null): mixed
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
     * Generate cache key for template
     */
    private function getCacheKey(string $name, string $path): string
    {
        return md5($name . '|' . $path . '|' . filemtime($path));
    }

    /**
     * Load template from cache
     */
    private function loadFromCache(string $cacheKey, string $originalPath): ?Template
    {
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
        
        if (!is_file($cacheFile)) {
            return null;
        }

        $cacheData = unserialize(file_get_contents($cacheFile));
        
        if ($cacheData === false || !isset($cacheData['template'], $cacheData['mtime'])) {
            return null;
        }

        // Check if original file was modified
        if (filemtime($originalPath) > $cacheData['mtime']) {
            unlink($cacheFile);
            return null;
        }

        return $cacheData['template'];
    }

    /**
     * Save template to cache
     */
    private function saveToCache(string $cacheKey, Template $template): void
    {
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
        $cacheData = [
            'template' => $template,
            'mtime' => time()
        ];

        file_put_contents($cacheFile, serialize($cacheData), LOCK_EX);
    }
} 