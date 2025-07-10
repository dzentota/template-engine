<?php

declare(strict_types=1);

namespace Dzentota\TemplateEngine\Security;

use Dzentota\TemplateVariable\TemplateVariable;
use Dzentota\TypedValue\TypedValue;
use InvalidArgumentException;

/**
 * Security Manager for Template Engine
 * 
 * Enforces security policies and provides security utilities
 * based on AppSec Manifesto principles
 */
class SecurityManager
{
    private array $allowedFunctions = [
        // Safe PHP functions that can be used in templates
        'count', 'empty', 'isset', 'is_array', 'is_string', 'is_numeric',
        'strlen', 'substr', 'strtolower', 'strtoupper', 'trim',
        'date', 'time', 'number_format', 'json_encode',
        'array_key_exists', 'array_keys', 'array_values', 'array_merge',
        'in_array', 'array_filter', 'array_map', 'array_slice'
    ];

    private array $blockedFunctions = [
        // Dangerous functions that should never be used in templates
        'eval', 'exec', 'system', 'shell_exec', 'passthru', 'proc_open',
        'file_get_contents', 'file_put_contents', 'fopen', 'fwrite',
        'include', 'include_once', 'require', 'require_once',
        'unlink', 'rmdir', 'mkdir', 'chmod', 'chown',
        'curl_exec', 'curl_init', 'mail', 'header'
    ];

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'strict_mode' => true,
            'allow_php_functions' => false,
            'max_template_size' => 1024 * 1024, // 1MB
            'max_recursion_depth' => 10,
            'sandbox_mode' => true
        ], $config);
    }

    /**
     * Validate template source code for security issues
     */
    public function validateTemplate(string $source, string $templateName): array
    {
        $issues = [];

        // Check for dangerous function calls
        foreach ($this->blockedFunctions as $function) {
            if (preg_match('/\b' . preg_quote($function) . '\s*\(/i', $source)) {
                $issues[] = [
                    'type' => 'security',
                    'severity' => 'high',
                    'message' => "Dangerous function '{$function}' detected in template '{$templateName}'"
                ];
            }
        }

        // Check for direct variable access (should use helper functions)
        if ($this->config['strict_mode']) {
            if (preg_match('/echo\s+\$[a-zA-Z_][a-zA-Z0-9_]*\s*;/', $source)) {
                $issues[] = [
                    'type' => 'security',
                    'severity' => 'medium',
                    'message' => "Direct variable output detected. Use helper functions for automatic escaping."
                ];
            }
        }

        // Check for PHP opening tags that might bypass security
        if (preg_match('/<\?(?!php\s)/i', $source)) {
            $issues[] = [
                'type' => 'security',
                'severity' => 'high',
                'message' => "Short PHP tags detected. Use full <?php tags for consistency."
            ];
        }

        // Check template size
        if (strlen($source) > $this->config['max_template_size']) {
            $issues[] = [
                'type' => 'performance',
                'severity' => 'medium',
                'message' => "Template size exceeds maximum allowed size"
            ];
        }

        // Check for potential XSS vectors
        if (preg_match('/javascript:/i', $source)) {
            $issues[] = [
                'type' => 'security',
                'severity' => 'high',
                'message' => "Potential XSS vector detected: javascript: protocol"
            ];
        }

        return $issues;
    }

    /**
     * Sanitize variable name for use in templates
     */
    public function sanitizeVariableName(string $name): string
    {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid variable name: {$name}");
        }

        // Check against reserved words
        $reserved = ['__FILE__', '__LINE__', '__CLASS__', '__METHOD__', '__FUNCTION__'];
        if (in_array(strtoupper($name), $reserved)) {
            throw new InvalidArgumentException("Variable name conflicts with PHP reserved word: {$name}");
        }

        return $name;
    }

    /**
     * Create secure context for different output types
     */
    public function createSecureVariable(mixed $value, string $context = 'html'): TemplateVariable
    {
        // Validate context
        $allowedContexts = ['html', 'attr', 'js', 'css', 'url', 'raw'];
        if (!in_array($context, $allowedContexts)) {
            throw new InvalidArgumentException("Invalid context: {$context}");
        }

        // Convert to TypedValue if needed
        if (!($value instanceof TypedValue)) {
            $value = TypedValue::create($value);
        }

        return new TemplateVariable($value, $context);
    }

    /**
     * Check if a function is allowed in templates
     */
    public function isFunctionAllowed(string $function): bool
    {
        if (in_array($function, $this->blockedFunctions)) {
            return false;
        }

        if (!$this->config['allow_php_functions']) {
            return in_array($function, $this->allowedFunctions);
        }

        return !in_array($function, $this->blockedFunctions);
    }

    /**
     * Generate Content Security Policy headers for templates
     */
    public function generateCSPHeaders(): array
    {
        return [
            "Content-Security-Policy" => implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: https:",
                "font-src 'self'",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'"
            ])
        ];
    }

    /**
     * Audit template for security compliance
     */
    public function auditTemplate(string $source, string $templateName): array
    {
        $audit = [
            'template' => $templateName,
            'timestamp' => date('Y-m-d H:i:s'),
            'issues' => $this->validateTemplate($source, $templateName),
            'security_score' => 0,
            'recommendations' => []
        ];

        // Calculate security score
        $totalIssues = count($audit['issues']);
        $highSeverityIssues = count(array_filter($audit['issues'], fn($issue) => $issue['severity'] === 'high'));
        
        if ($totalIssues === 0) {
            $audit['security_score'] = 100;
        } else {
            $audit['security_score'] = max(0, 100 - ($highSeverityIssues * 30) - (($totalIssues - $highSeverityIssues) * 10));
        }

        // Generate recommendations
        if ($highSeverityIssues > 0) {
            $audit['recommendations'][] = "Address high-severity security issues immediately";
        }

        if ($totalIssues > 5) {
            $audit['recommendations'][] = "Consider refactoring template to reduce complexity";
        }

        if ($audit['security_score'] < 80) {
            $audit['recommendations'][] = "Template requires security review before production use";
        }

        return $audit;
    }

    /**
     * Get security configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Update security configuration
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
} 