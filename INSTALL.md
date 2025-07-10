# Installation Guide

## Requirements

- PHP 8.1 or higher
- Composer

## Quick Installation

1. **Install via Composer**
   ```bash
   # For stable projects, adjust minimum-stability in your composer.json
   composer require dzentota/template-engine
   
   # Or install with dev dependencies allowed
   composer require dzentota/template-engine --prefer-source
   ```

2. **Basic Setup**
   ```php
   <?php
   require_once 'vendor/autoload.php';
   
   use Dzentota\TemplateEngine\TemplateEngine;
   
   $engine = new TemplateEngine();
   $engine->addPath(__DIR__ . '/templates');
   
   echo $engine->render('welcome', ['name' => 'World']);
   ```

3. **Create Your First Template** (`templates/welcome.php`)
   ```php
   <!DOCTYPE html>
   <html>
   <head>
       <title>Welcome</title>
   </head>
   <body>
       <h1>Hello, <?= $name ?>!</h1>
   </body>
   </html>
   ```

## Development Setup

1. **Clone Repository**
   ```bash
   git clone https://github.com/dzentota/template-engine.git
   cd template-engine
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Run Tests**
   ```bash
   composer test
   ```

4. **Check Code Quality**
   ```bash
   composer phpstan
   composer cs-check
   ```

## Configuration

### Production Setup
```php
$engine = new TemplateEngine([
    'auto_escape' => true,
    'cache' => true,
    'cache_dir' => '/path/to/cache',
    'debug' => false
]);
```

### Development Setup
```php
$engine = new TemplateEngine([
    'auto_escape' => true,
    'debug' => true,
    'strict_variables' => true
]);
```

## Directory Structure

```
project/
├── templates/
│   ├── layout.php
│   ├── pages/
│   │   ├── home.php
│   │   └── about.php
│   └── partials/
│       ├── header.php
│       └── footer.php
├── cache/              # Template cache (production)
└── public/
    └── index.php       # Your application
```

## Next Steps

1. Read the [README.md](README.md) for detailed documentation
2. Check out the [examples/](examples/) directory
3. Run the security audit example: `php examples/security_audit.php`
4. Visit the examples: `php examples/basic_usage.php`

## Support

- Documentation: [README.md](README.md)
- Examples: [examples/](examples/)
- Issues: GitHub Issues
- Security: security@dzentota.com 