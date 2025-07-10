<?php

declare(strict_types=1);

namespace Dzentota\TemplateEngine\Tests;

use Dzentota\TemplateEngine\TemplateEngine;
use Dzentota\TemplateEngine\Template;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use InvalidArgumentException;

class TemplateEngineTest extends TestCase
{
    private TemplateEngine $engine;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/template_engine_test_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/templates');

        $this->engine = new TemplateEngine([
            'auto_escape' => true,
            'debug' => true
        ]);
        
        $this->engine->addPath($this->tempDir . '/templates');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTemplate(string $name, string $content): void
    {
        $path = $this->tempDir . '/templates/' . $name . '.php';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    public function testBasicRendering(): void
    {
        $this->createTemplate('basic', '<h1><?= $title ?></h1>');
        
        $output = $this->engine->render('basic', ['title' => 'Hello World']);
        
        $this->assertEquals('<h1>Hello World</h1>', $output);
    }

    public function testXSSProtection(): void
    {
        $this->createTemplate('xss', '<p><?= $content ?></p>');
        
        $output = $this->engine->render('xss', [
            'content' => '<script>alert("XSS")</script>'
        ]);
        
        $this->assertEquals('<p>&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;</p>', $output);
    }

    public function testGlobalVariables(): void
    {
        $this->engine->addGlobal('app_name', 'Test App');
        
        $this->createTemplate('globals', '<title><?= $app_name ?></title>');
        
        $output = $this->engine->render('globals');
        
        $this->assertEquals('<title>Test App</title>', $output);
    }

    public function testTemplateIncludes(): void
    {
        $this->createTemplate('header', '<header><?= $title ?></header>');
        $this->createTemplate('main', '<?= $include("header", ["title" => "Main Page"]) ?><main>Content</main>');
        
        $output = $this->engine->render('main');
        
        $this->assertEquals('<header>Main Page</header><main>Content</main>', $output);
    }

    public function testContextAwareEscaping(): void
    {
        $template = '
            <p><?= $text ?></p>
            <input value="<?= $text(\'attr\') ?>">
            <script>var data = <?= $text(\'js\') ?>;</script>
        ';
        
        $this->createTemplate('contexts', $template);
        
        $output = $this->engine->render('contexts', [
            'text' => '"Hello & <World>"'
        ]);
        
        $this->assertStringContainsString('&quot;Hello &amp; &lt;World&gt;&quot;', $output); // HTML escaped
        $this->assertStringContainsString('&quot;Hello &amp; &lt;World&gt;&quot;', $output); // Attr escaped
        $this->assertStringContainsString('\"Hello \\u0026 \\u003CWorld\\u003E\"', $output); // JS escaped
    }

    public function testTemplateNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template not found or outside allowed directory');
        
        $this->engine->render('nonexistent');
    }

    public function testInvalidTemplatePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Template directory does not exist');
        
        $this->engine->addPath('/nonexistent/path');
    }

    public function testDirectoryTraversalProtection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template not found or outside allowed directory');
        
        $this->engine->render('../../../etc/passwd');
    }

    public function testTemplateConfiguration(): void
    {
        $config = [
            'auto_escape' => false,
            'debug' => true,
            'default_context' => 'attr'
        ];
        
        $engine = new TemplateEngine($config);
        
        $this->assertEquals($config['auto_escape'], $engine->getConfig('auto_escape'));
        $this->assertEquals($config['debug'], $engine->getConfig('debug'));
        $this->assertEquals($config['default_context'], $engine->getConfig('default_context'));
    }

    public function testNamespacedTemplates(): void
    {
        mkdir($this->tempDir . '/admin');
        $this->engine->addPath($this->tempDir . '/admin', 'admin');
        
        $this->createTemplate('../admin/dashboard', '<h1>Admin Dashboard</h1>');
        
        $output = $this->engine->render('@admin/dashboard');
        
        $this->assertEquals('<h1>Admin Dashboard</h1>', $output);
    }

    public function testTemplateMetadata(): void
    {
        $this->createTemplate('meta', '<p>Test</p>');
        
        $template = $this->engine->load('meta');
        $metadata = $template->getMetadata();
        
        $this->assertEquals('meta', $metadata['name']);
        $this->assertArrayHasKey('path', $metadata);
        $this->assertArrayHasKey('size', $metadata);
        $this->assertArrayHasKey('modified', $metadata);
        $this->assertTrue($metadata['features']['escaping']);
        $this->assertTrue($metadata['features']['native_php']);
        $this->assertTrue($metadata['features']['security']);
    }

    public function testRawOutput(): void
    {
        $this->createTemplate('raw', '<div><?= $content(\'raw\') ?></div>');
        
        $output = $this->engine->render('raw', [
            'content' => '<strong>Bold</strong>'
        ]);
        
        $this->assertEquals('<div><strong>Bold</strong></div>', $output);
    }

    public function testMultipleGlobals(): void
    {
        $globals = [
            'site_name' => 'My Site',
            'version' => '2.0.0'
        ];
        
        $this->engine->addGlobals($globals);
        
        $retrievedGlobals = $this->engine->getGlobals();
        
        $this->assertEquals($globals['site_name'], $retrievedGlobals['site_name']);
        $this->assertEquals($globals['version'], $retrievedGlobals['version']);
    }

    public function testTemplateWithPHPLogic(): void
    {
        $template = '
            <ul>
            <?php foreach ($items as $item): ?>
                <li><?= $item ?></li>
            <?php endforeach; ?>
            </ul>
        ';
        
        $this->createTemplate('list', $template);
        
        $output = $this->engine->render('list', [
            'items' => ['Item 1', 'Item 2', 'Item 3']
        ]);
        
        $this->assertStringContainsString('<li>Item 1</li>', $output);
        $this->assertStringContainsString('<li>Item 2</li>', $output);
        $this->assertStringContainsString('<li>Item 3</li>', $output);
    }
} 