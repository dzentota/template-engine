<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dzentota\TemplateEngine\Security\SecurityManager;

// Initialize the security manager
$security = new SecurityManager([
    'strict_mode' => true,
    'allow_php_functions' => false,
    'max_template_size' => 1024 * 1024
]);

// Example templates to audit
$templates = [
    'secure_template.php' => '
        <!DOCTYPE html>
        <html>
        <head>
            <title><?= $e($title) ?></title>
        </head>
        <body>
            <h1><?= $e($heading) ?></h1>
            <p><?= $e($content) ?></p>
            <a href="<?= $url($link) ?>"><?= $e($linkText) ?></a>
        </body>
        </html>
    ',
    
    'insecure_template.php' => '
        <!DOCTYPE html>
        <html>
        <head>
            <title><?= $title ?></title>  <!-- Missing escaping -->
        </head>
        <body>
            <h1><?= $heading ?></h1>  <!-- Missing escaping -->
            <?= file_get_contents($filename) ?>  <!-- Dangerous function -->
            <script>
                var data = "<?= $data ?>";  <!-- Wrong context escaping -->
            </script>
        </body>
        </html>
    ',
    
    'mixed_template.php' => '
        <!DOCTYPE html>
        <html>
        <body>
            <h1><?= $e($title) ?></h1>  <!-- Good: properly escaped -->
            <p><?= $content ?></p>      <!-- Bad: missing escaping -->
            <a href="javascript:alert(1)">Click</a>  <!-- Bad: XSS vector -->
        </body>
        </html>
    '
];

echo "Template Security Audit Report\n";
echo "===============================\n\n";

foreach ($templates as $templateName => $templateContent) {
    echo "Auditing: {$templateName}\n";
    echo str_repeat('-', 40) . "\n";
    
    // Audit the template
    $audit = $security->auditTemplate($templateContent, $templateName);
    
    // Display results
    echo "Security Score: {$audit['security_score']}/100\n";
    echo "Issues Found: " . count($audit['issues']) . "\n\n";
    
    if (!empty($audit['issues'])) {
        echo "Issues:\n";
        foreach ($audit['issues'] as $issue) {
            $severity = strtoupper($issue['severity']);
            echo "  [{$severity}] {$issue['message']}\n";
        }
        echo "\n";
    }
    
    if (!empty($audit['recommendations'])) {
        echo "Recommendations:\n";
        foreach ($audit['recommendations'] as $recommendation) {
            echo "  • {$recommendation}\n";
        }
        echo "\n";
    }
    
    echo "\n";
}

// Generate CSP headers
echo "Content Security Policy Headers\n";
echo "===============================\n";
$cspHeaders = $security->generateCSPHeaders();
foreach ($cspHeaders as $header => $value) {
    echo "{$header}: {$value}\n";
}
echo "\n";

// Function whitelist check
echo "Function Security Check\n";
echo "======================\n";
$functionsToCheck = [
    'htmlspecialchars',  // Safe
    'file_get_contents', // Dangerous
    'count',            // Safe
    'eval',             // Dangerous
    'array_map'         // Safe
];

foreach ($functionsToCheck as $function) {
    $status = $security->isFunctionAllowed($function) ? '✅ ALLOWED' : '❌ BLOCKED';
    echo "{$function}: {$status}\n";
}

echo "\nSecurity audit complete!\n"; 