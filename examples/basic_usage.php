<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dzentota\TemplateEngine\TemplateEngine;

// Initialize the template engine
$engine = new TemplateEngine([
    'auto_escape' => true,
    'strict_variables' => true,
    'debug' => true,
    'cache' => false
]);

// Add template directory
$engine->addPath(__DIR__ . '/templates');

// Add global variables
$engine->addGlobal('app_name', 'My Secure App');
$engine->addGlobal('version', '1.0.0');

// Template variables
$data = [
    'title' => 'Welcome to Secure Templates',
    'user' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'bio' => '<script>alert("XSS")</script>This is my bio with HTML content'
    ],
    'items' => [
        ['name' => 'Item 1', 'price' => 19.99],
        ['name' => 'Item 2', 'price' => 29.99],
        ['name' => 'Item 3', 'price' => 39.99]
    ],
    'unsafe_html' => '<img src="x" onerror="alert(\'XSS\')">'
];

// Render the template
try {
    $output = $engine->render('welcome', $data);
    echo $output;
} catch (Exception $e) {
    echo "Template error: " . $e->getMessage();
} 