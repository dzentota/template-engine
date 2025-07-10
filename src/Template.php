<?php

declare(strict_types=1);

namespace Dzentota\TemplateEngine;

use Dzentota\TemplateVariable\TemplateVariable;
use Dzentota\TemplateVariable\TemplateVariableCollection;
use RuntimeException;
use Throwable;

/**
 * Template class for secure template rendering
 * 
 * Provides native PHP syntax with automatic escaping
 * and secure variable access
 */
class Template
{
    private TemplateEngine $engine;
    private string $path;
    private string $name;
    private ?string $source = null;

    public function __construct(TemplateEngine $engine, string $path, string $name)
    {
        $this->engine = $engine;
        $this->path = $path;
        $this->name = $name;
    }

    /**
     * Render the template with given variables
     */
    public function render(array $variables = []): string
    {
        try {
            // Merge template variables with globals
            $templateVars = $this->prepareVariables($variables);
            
            // Start output buffering
            ob_start();
            
            // Create secure execution environment
            $this->execute($templateVars);
            
            return ob_get_clean();
            
        } catch (Throwable $e) {
            // Clean up output buffer on error
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            if ($this->engine->getConfig('debug')) {
                throw new RuntimeException(
                    "Template rendering failed for '{$this->name}': " . $e->getMessage(),
                    0,
                    $e
                );
            }
            
            throw new RuntimeException("Template rendering failed for '{$this->name}'");
        }
    }

    /**
     * Get template source code
     */
    public function getSource(): string
    {
        if ($this->source === null) {
            $this->source = file_get_contents($this->path);
            
            if ($this->source === false) {
                throw new RuntimeException("Cannot read template file: {$this->path}");
            }
        }

        return $this->source;
    }

    /**
     * Get template file path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get template name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Prepare variables for template execution
     */
    private function prepareVariables(array $variables): array
    {
        $globals = $this->engine->getGlobals();
        $merged = array_merge($globals, $variables);
        $prepared = [];

        foreach ($merged as $name => $value) {
            // Convert to secure template variables
            if ($value instanceof TemplateVariable || $value instanceof TemplateVariableCollection) {
                $prepared[$name] = $value;
            } else {
                $prepared[$name] = $this->engine->createVariable($value);
            }
        }

        return $prepared;
    }

    /**
     * Execute template in secure environment
     */
    private function execute(array $variables): void
    {
        // Helper functions for templates (optional - TemplateVariable provides magic methods)
        $include = function(string $template, array $vars = []) use ($variables): string {
            $mergedVars = array_merge($variables, $vars);
            return $this->engine->render($template, $mergedVars);
        };

        $asset = function(string $path): string {
            // Basic asset helper - can be extended
            return $this->engine->createVariable($path, 'url');
        };

        // Legacy helper functions for backward compatibility
        $e = function(mixed $value): TemplateVariable|TemplateVariableCollection {
            if ($value instanceof TemplateVariable || $value instanceof TemplateVariableCollection) {
                return $value;
            }
            return $this->engine->createVariable($value);
        };

        $raw = function(mixed $value): string {
            if ($value instanceof TemplateVariable) {
                return $value('raw');
            }
            $var = $this->engine->createVariable($value);
            return $var instanceof TemplateVariable ? $var('raw') : (string)$var;
        };

        // Extract variables into local scope - they are now TemplateVariable instances
        extract($variables, EXTR_SKIP);

        // Include template file
        include $this->path;
    }

    /**
     * Check if template supports a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return match($feature) {
            'escaping' => true,
            'inheritance' => false, // Can be extended
            'blocks' => false,      // Can be extended
            'macros' => false,      // Can be extended
            default => false
        };
    }

    /**
     * Get template metadata
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'size' => filesize($this->path),
            'modified' => filemtime($this->path),
            'features' => [
                'escaping' => true,
                'native_php' => true,
                'security' => true
            ]
        ];
    }
} 