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

    // -------------------------------------------------------------------------
    // Caching
    // -------------------------------------------------------------------------

    public function testCacheHit(): void
    {
        $cacheDir = $this->tempDir . '/cache';
        mkdir($cacheDir);

        $engine = new TemplateEngine(['cache' => true, 'debug' => true]);
        $engine->addPath($this->tempDir . '/templates');
        $engine->setCacheDirectory($cacheDir);

        $this->createTemplate('cached', '<p>cached</p>');

        // First render — populates the cache
        $output1 = $engine->render('cached');
        $this->assertEquals('<p>cached</p>', $output1);

        // A .json cache file must exist
        $cacheFiles = glob($cacheDir . '/*.json');
        $this->assertNotEmpty($cacheFiles, 'Cache file should exist after first render');

        // Second render — must return same output (served from cache)
        $output2 = $engine->render('cached');
        $this->assertEquals($output1, $output2);
    }

    public function testCacheInvalidatedOnFileChange(): void
    {
        $cacheDir = $this->tempDir . '/cache';
        mkdir($cacheDir);

        $engine = new TemplateEngine(['cache' => true, 'debug' => true]);
        $engine->addPath($this->tempDir . '/templates');
        $engine->setCacheDirectory($cacheDir);

        $this->createTemplate('changing', '<p>version1</p>');
        $engine->render('changing');

        // Overwrite template and set a future mtime so the cache sees a change
        $templatePath = $this->tempDir . '/templates/changing.php';
        file_put_contents($templatePath, '<p>version2</p>');
        touch($templatePath, time() + 10);

        $output = $engine->render('changing');
        $this->assertEquals('<p>version2</p>', $output);
    }

    public function testCorruptCacheFileIsIgnored(): void
    {
        $cacheDir = $this->tempDir . '/cache';
        mkdir($cacheDir);

        $engine = new TemplateEngine(['cache' => true, 'debug' => true]);
        $engine->addPath($this->tempDir . '/templates');
        $engine->setCacheDirectory($cacheDir);

        $this->createTemplate('corrupt', '<p>ok</p>');

        // Prime cache
        $engine->render('corrupt');

        // Corrupt all cache files
        foreach (glob($cacheDir . '/*.json') as $file) {
            file_put_contents($file, 'NOT_VALID_JSON{{{');
        }

        // Should still render correctly (falls back to disk)
        $output = $engine->render('corrupt');
        $this->assertEquals('<p>ok</p>', $output);
    }

    public function testCacheDirectoryPermissions(): void
    {
        $cacheDir = $this->tempDir . '/newcache';

        $engine = new TemplateEngine(['cache' => true]);
        $engine->addPath($this->tempDir . '/templates');
        $engine->setCacheDirectory($cacheDir);

        $perms = fileperms($cacheDir) & 0777;
        $this->assertEquals(0700, $perms, 'Cache directory must be owner-only (0700)');
    }

    // -------------------------------------------------------------------------
    // Namespace validation
    // -------------------------------------------------------------------------

    public function testInvalidNamespaceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid namespace');

        mkdir($this->tempDir . '/ns');
        $this->engine->addPath($this->tempDir . '/ns', 'invalid-namespace!');
    }

    public function testValidNamespaceAccepted(): void
    {
        mkdir($this->tempDir . '/ns2');
        // Should not throw
        $this->engine->addPath($this->tempDir . '/ns2', 'my_namespace2');
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // SecurityManager
    // -------------------------------------------------------------------------

    public function testSecurityManagerBlocksDangerousFunctions(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $source = '<?php echo exec("ls"); ?>';
        $issues = $manager->validateTemplate($source, 'test.php');

        $this->assertNotEmpty($issues);
        $types = array_column($issues, 'severity');
        $this->assertContains('high', $types);
    }

    public function testSecurityManagerDetectsShortTags(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $source = '<? echo $var; ?>';
        $issues = $manager->validateTemplate($source, 'test.php');

        $messages = array_column($issues, 'message');
        $found = array_filter($messages, fn($m) => str_contains($m, 'Short PHP tags'));
        $this->assertNotEmpty($found);
    }

    public function testSecurityManagerDetectsJavascriptProtocol(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $source = '<a href="javascript:alert(1)">click</a>';
        $issues = $manager->validateTemplate($source, 'test.php');

        $messages = array_column($issues, 'message');
        $found = array_filter($messages, fn($m) => str_contains($m, 'XSS'));
        $this->assertNotEmpty($found);
    }

    public function testSecurityManagerCleanTemplateHasNoIssues(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $source = '<?php echo htmlspecialchars($name); ?>';
        $issues = $manager->validateTemplate($source, 'clean.php');

        $this->assertEmpty($issues);
    }

    public function testIsFunctionAllowedBlocksDangerousFunctions(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $this->assertFalse($manager->isFunctionAllowed('eval'));
        $this->assertFalse($manager->isFunctionAllowed('exec'));
        $this->assertFalse($manager->isFunctionAllowed('file_get_contents'));
    }

    public function testIsFunctionAllowedPermitsSafeFunctions(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $this->assertTrue($manager->isFunctionAllowed('count'));
        $this->assertTrue($manager->isFunctionAllowed('strtolower'));
        $this->assertTrue($manager->isFunctionAllowed('date'));
    }

    public function testSanitizeVariableNameAcceptsValid(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $this->assertEquals('myVar', $manager->sanitizeVariableName('myVar'));
        $this->assertEquals('_private', $manager->sanitizeVariableName('_private'));
        $this->assertEquals('item123', $manager->sanitizeVariableName('item123'));
    }

    public function testSanitizeVariableNameRejectsInvalid(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $this->expectException(InvalidArgumentException::class);
        $manager->sanitizeVariableName('123invalid');
    }

    public function testSanitizeVariableNameRejectsReservedWords(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $this->expectException(InvalidArgumentException::class);
        $manager->sanitizeVariableName('__FILE__');
    }

    public function testGenerateCSPHeadersReturnsValidHeader(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $headers = $manager->generateCSPHeaders();

        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $csp = $headers['Content-Security-Policy'];
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function testAuditTemplateReturnsScoreAndRecommendations(): void
    {
        $manager = new \Dzentota\TemplateEngine\Security\SecurityManager();

        $source = '<?php exec("rm -rf /"); ?>';
        $audit = $manager->auditTemplate($source, 'dangerous.php');

        $this->assertArrayHasKey('security_score', $audit);
        $this->assertArrayHasKey('issues', $audit);
        $this->assertArrayHasKey('recommendations', $audit);
        $this->assertLessThan(100, $audit['security_score']);
    }
} 