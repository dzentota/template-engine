# dzentota/template-engine

A secure template engine for PHP with native syntax and context-aware escaping, built on top of `dzentota/template-variable` and `dzentota/typedvalue`.

## Features

🔒 **Security First**: Built following the [AppSec Manifesto](https://github.com/dzentota/AppSecManifesto) principles
- Context-aware automatic escaping (HTML, attributes, JavaScript, CSS, URLs)
- Protection against XSS attacks
- Secure by default configuration
- Template security auditing

🚀 **Native PHP Syntax**: Similar to bareui, uses familiar PHP syntax
- No custom template language to learn
- Full PHP power when needed
- Clean, readable templates

⚡ **Performance**: 
- Optional template caching
- Optimized for production use
- Minimal overhead

🛡️ **Developer Experience**:
- Comprehensive error handling
- Debug mode for development
- Template includes and partials
- Global variables support
- Namespaced template directories

## Installation

```bash
composer require dzentota/template-engine
```

**Note:** This package depends on development versions of `dzentota/template-variable` and `dzentota/typedvalue`. You may need to adjust your `minimum-stability` setting in `composer.json` to `"dev"` or use `--prefer-source` flag during installation.

## Quick Start

```php
<?php
use Dzentota\TemplateEngine\TemplateEngine;

// Initialize the engine
$engine = new TemplateEngine([
    'auto_escape' => true,
    'debug' => true
]);

// Add template directory
$engine->addPath(__DIR__ . '/templates');

// Render template with data
echo $engine->render('welcome', [
    'title' => 'Hello World',
    'user' => ['name' => 'John Doe']
]);
```

**Template (templates/welcome.php):**
```php
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?></title>
</head>
<body>
    <h1>Welcome, <?= $user['name'] ?>!</h1>
</body>
</html>
```

## Security Features

### Automatic Context-Aware Escaping

The template engine wraps all variables as `TemplateVariable` instances, which provide automatic escaping through magic methods:

- `<?= $variable ?>` - Uses `__toString()` for HTML context escaping
- `<?= $variable('attr') ?>` - Uses `__invoke('attr')` for attribute context escaping
- `<?= $variable('js') ?>` - Uses `__invoke('js')` for JavaScript context escaping

The template engine automatically escapes variables based on their output context:

```php
<!-- HTML Context (default via __toString magic method) -->
<p><?= $userInput ?></p>

<!-- Attribute Context -->
<input value="<?= $userInput('attr') ?>">

<!-- JavaScript Context -->
<script>var data = <?= $userInput('js') ?>;</script>

<!-- CSS Context -->
<style>.class { color: <?= $userInput('css') ?>; }</style>

<!-- URL Context -->
<a href="<?= $userInput('url') ?>">Link</a>

<!-- Raw Output (use with extreme caution) -->
<div><?= $trustedContent('raw') ?></div>
```

### XSS Protection

All variables are automatically escaped unless explicitly marked as raw:

```php
$data = [
    'safe_text' => 'Hello World',
    'unsafe_html' => '<script>alert("XSS")</script>'
];

// This is safe - script tags will be escaped
echo $engine->render('template', $data);
```

**Template:**
```php
<p><?= $unsafe_html ?></p>
<!-- Output: &lt;script&gt;alert("XSS")&lt;/script&gt; -->
```

## Template Syntax

### Variables

```php
<!-- Escaped output (automatic via __toString) -->
<?= $variable ?>

<!-- Different contexts -->
<?= $variable('attr') ?>  <!-- For HTML attributes -->
<?= $variable('js') ?>    <!-- For JavaScript -->
<?= $variable('css') ?>   <!-- For CSS values -->
<?= $variable('url') ?>   <!-- For URLs -->
<?= $variable('raw') ?>   <!-- Raw/unescaped (use with caution) -->
```

### Control Structures

Use native PHP syntax:

```php
<!-- Conditionals -->
<?php if ($user['is_admin']): ?>
    <p>Admin panel available</p>
<?php endif; ?>

<!-- Loops -->
<?php foreach ($items as $item): ?>
    <div><?= $item['name'] ?></div>
<?php endforeach; ?>

<!-- Switch statements -->
<?php switch ($user['role']): ?>
    <?php case 'admin': ?>
        <p>Administrator</p>
        <?php break; ?>
    <?php case 'user': ?>
        <p>Regular user</p>
        <?php break; ?>
<?php endswitch; ?>
```

### Template Includes

```php
<!-- Include other templates -->
<?= $include('partials/header', ['title' => 'Page Title']) ?>

<!-- Content here -->

<?= $include('partials/footer') ?>
```

### Global Variables

```php
// Set global variables
$engine->addGlobal('app_name', 'My App');
$engine->addGlobal('version', '1.0.0');

// Use in templates
<p><?= $app_name ?> v<?= $version ?></p>
```

## Configuration

```php
$engine = new TemplateEngine([
    'auto_escape' => true,           // Enable automatic escaping
    'strict_variables' => true,      // Throw errors for undefined variables
    'debug' => false,                // Enable debug mode
    'cache' => true,                 // Enable template caching
    'cache_dir' => '/tmp/templates', // Cache directory
    'default_context' => 'html',     // Default escaping context
    'file_extension' => '.php'       // Template file extension
]);
```

## Template Directories

### Single Directory

```php
$engine->addPath('/path/to/templates');
```

### Multiple Namespaced Directories

Namespace names must be valid identifiers: start with a letter or underscore, followed by letters, digits, or underscores (`[a-zA-Z_][a-zA-Z0-9_]*`).

```php
$engine->addPath('/path/to/app/templates', 'app');
$engine->addPath('/path/to/admin/templates', 'admin');

// Use namespaced templates
echo $engine->render('@admin/dashboard');
echo $engine->render('@app/welcome');
```

Invalid namespace names throw an `InvalidArgumentException`:

```php
// ❌ Throws InvalidArgumentException — dashes are not allowed
$engine->addPath('/path/to/templates', 'my-namespace');

// ✅ OK
$engine->addPath('/path/to/templates', 'my_namespace');
```

## Security Auditing

`SecurityManager` is a **static analysis tool** for auditing template source code before deployment. It detects dangerous function calls, short PHP tags, XSS vectors, and oversized templates, and produces a scored report with recommendations.

> **Note:** `SecurityManager` performs source-level auditing only. It does not intercept or block function calls at runtime. Use it in your CI pipeline or development workflow to catch issues early.

```php
use Dzentota\TemplateEngine\Security\SecurityManager;

$security = new SecurityManager();

// Audit a template
$templateSource = file_get_contents('template.php');
$audit = $security->auditTemplate($templateSource, 'template.php');

echo "Security Score: " . $audit['security_score'] . "/100\n";

foreach ($audit['issues'] as $issue) {
    echo "- " . $issue['message'] . " (Severity: " . $issue['severity'] . ")\n";
}
```

## Advanced Usage

### Content Security Policy

```php
$security = new SecurityManager();
$headers = $security->generateCSPHeaders();

foreach ($headers as $name => $value) {
    header("$name: $value");
}
```

### Template Metadata

```php
$template = $engine->load('welcome');
$metadata = $template->getMetadata();

echo "Template: " . $metadata['name'] . "\n";
echo "Size: " . $metadata['size'] . " bytes\n";
echo "Modified: " . date('Y-m-d H:i:s', $metadata['modified']) . "\n";
```

## Error Handling

```php
try {
    $output = $engine->render('template', $data);
    echo $output;
} catch (RuntimeException $e) {
    // Template not found, rendering error, etc.
    error_log("Template error: " . $e->getMessage());
    echo "Template error occurred";
} catch (InvalidArgumentException $e) {
    // Invalid configuration, variable names, etc.
    error_log("Configuration error: " . $e->getMessage());
}
```

## Best Practices

### 1. Use TemplateVariable for Automatic Escaping

```php
<!-- ✅ Good - automatic escaping via TemplateVariable -->
<p><?= $userInput ?></p>

<!-- ❌ Bad - raw PHP variable (bypasses TemplateVariable) -->
<p><?= $_GET['user_input'] ?></p>
```

### 2. Use Context-Appropriate Escaping

```php
<!-- ✅ Good -->
<input value="<?= $value('attr') ?>">
<script>var data = <?= $data('js') ?>;</script>

<!-- ❌ Bad -->
<input value="<?= $value ?>">
<script>var data = "<?= $data ?>";</script>
```

### 3. Validate Template Security

```php
// In development, audit your templates
if ($developmentMode) {
    $security = new SecurityManager();
    $audit = $security->auditTemplate($templateSource, $templateName);
    
    if ($audit['security_score'] < 80) {
        throw new Exception("Template security score too low: " . $audit['security_score']);
    }
}
```

### 4. Use Template Caching in Production

Cache files are stored as JSON with `0700` permissions (owner-readable only). The cache is invalidated automatically when a template file is modified.

```php
$engine = new TemplateEngine([
    'cache' => true,
    'cache_dir' => sys_get_temp_dir() . '/template_cache'
]);
```

## Examples

See the `examples/` directory for complete working examples:

- `basic_usage.php` - Basic template rendering
- `templates/welcome.php` - Comprehensive template with security features
- `templates/partials/` - Template includes and partials

## Security

This template engine is designed with security as a primary concern:

- **XSS Prevention**: All output is escaped by default
- **Context Awareness**: Different escaping for HTML, attributes, JS, CSS, URLs
- **Directory Traversal Protection**: Templates cannot access files outside designated directories using `realpath()` checks
- **Namespace Validation**: Template namespace names are validated as proper identifiers
- **Secure Cache Storage**: Cache files use JSON (no PHP object serialization), are written atomically, and stored in a `0700`-permissions directory
- **Security Auditing**: Built-in static analysis tools to audit template source code

## Requirements

- PHP 8.1 or higher
- `dzentota/template-variable` (dev-main)
- `dzentota/typedvalue` (dev-master)

## License

MIT License - see LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## Support

For issues and questions:
- GitHub Issues: [Report a bug](https://github.com/dzentota/template-engine/issues)
- Security Issues: Email webtota@gmail.com 