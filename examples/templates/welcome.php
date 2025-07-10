<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - <?= $app_name ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .user-card { border: 1px solid #ddd; padding: 20px; margin: 20px 0; }
        .items { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .item { border: 1px solid #ccc; padding: 15px; }
        .price { font-weight: bold; color: #007bff; }
        .danger { color: red; font-weight: bold; }
        .safe { color: green; }
    </style>
</head>
<body>
    <h1><?= $title ?></h1>
    
    <div class="user-card">
        <h2>User Information</h2>
        <!-- Secure HTML context output (automatic via __toString) -->
        <p><strong>Name:</strong> <?= $user['name'] ?></p>
        
        <!-- Secure attribute context for email link -->
        <p><strong>Email:</strong> <a href="mailto:<?= $user['email']('attr') ?>"><?= $user['email'] ?></a></p>
        
        <!-- Bio with automatic XSS protection -->
        <p><strong>Bio:</strong> <?= $user['bio'] ?></p>
        
        <!-- Demonstrating raw output (use with caution) -->
        <p class="danger"><strong>Raw Bio (DANGEROUS):</strong> <?= $user['bio']('raw') ?></p>
    </div>

    <h2>Product Items</h2>
    <div class="items">
        <?php foreach ($items as $item): ?>
            <div class="item">
                <h3><?= $item['name'] ?></h3>
                <p class="price">$<?= $item['price'] ?></p>
                
                <!-- URL context for product links -->
                <a href="<?= $item['name']('url') ?>">View Details</a>
            </div>
        <?php endforeach; ?>
    </div>

    <h2>Security Demonstration</h2>
    <div class="user-card">
        <p class="danger">Unsafe HTML (properly escaped): <?= $unsafe_html ?></p>
        <p class="safe">The XSS attempt above was automatically neutralized!</p>
        
        <!-- JavaScript context demonstration -->
        <script>
            var userName = <?= $user['name']('js') ?>;
            console.log('User name safely injected into JS:', userName);
        </script>
        
        <!-- CSS context demonstration -->
        <div style="color: <?= $user['name']('css') ?>;">
            This text color is safely injected into CSS
        </div>
    </div>

    <h2>Template Includes</h2>
    <?= $include('partials/footer', ['year' => date('Y')]) ?>

    <h2>Global Variables</h2>
    <p>App: <?= $app_name ?> v<?= $version ?></p>

    <!-- Advanced: Conditional rendering with security -->
    <?php if (!empty($user['name'])): ?>
        <div class="user-card">
            <h3>Welcome back, <?= $user['name'] ?>!</h3>
            <p>Your account is active.</p>
        </div>
    <?php endif; ?>
</body>
</html> 