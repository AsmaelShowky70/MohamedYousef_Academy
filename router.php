<?php
// Local Built-in PHP Server Router
// Usage: php -S 127.0.0.1:8000 router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// 1. Static files directly served if they exist
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// 2. Identity and upload aliases
if (preg_match('#^/identity/(.+)$#', $uri, $m)) {
    $target = __DIR__ . '/public/identity/' . $m[1];
    if (file_exists($target)) {
        header('Content-Type: ' . mime_content_type($target));
        readfile($target);
        return true;
    }
}
if ($uri === '/profile-bg.png') {
    $target = __DIR__ . '/public/profile-bg.png';
    if (file_exists($target)) {
        header('Content-Type: image/png');
        readfile($target);
        return true;
    }
}
if (preg_match('#^/designs/(.+)$#', $uri, $m)) {
    $target = __DIR__ . '/public/designs/' . $m[1];
    if (file_exists($target)) {
        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        if ($ext === 'svg') header('Content-Type: image/svg+xml');
        elseif ($ext === 'png') header('Content-Type: image/png');
        elseif ($ext === 'jpg' || $ext === 'jpeg') header('Content-Type: image/jpeg');
        else header('Content-Type: ' . mime_content_type($target));
        readfile($target);
        return true;
    }
}

// 3. Clean URL routes
$routes = [
    '/' => '/index.php',
    '/courses' => '/courses.php',
    '/course' => '/course.php',
    '/parent' => '/parent.php',
    '/parent/dashboard' => '/parent.php',
    '/profile' => '/profile.php',
    '/profile/wallet' => '/wallet.php',
    '/wallet' => '/wallet.php',
    '/profile/my-courses' => '/profile.php',
    '/profile/my-quizzes' => '/profile.php',
    '/profile/code' => '/profile.php',
    '/profile/collection-code' => '/profile.php',
    '/course/purchase' => '/purchase.php',
    '/purchase' => '/purchase.php',
    '/about-us' => '/about-us.php',
    '/contact-us' => '/contact-us.php',
    '/policies/privacy' => '/privacy.php',
    '/policies/terms-and-conditions' => '/terms.php',
    '/policies/exchange-return' => '/exchange.php',
    '/login' => '/login.php',
    '/register' => '/register.php',
    '/logout' => '/logout.php',
    '/admin' => '/admin/index.php',
    '/admin/' => '/admin/index.php',
    '/collection' => '/collection.php',
];

// Special pattern: /collection/:id
if (preg_match('#^/collection/([^/]+)/?$#', $uri, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/collection.php';
    return true;
}

// Special pattern: /year/:id
if (preg_match('#^/year/([^/]+)/?$#', $uri, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/year.php';
    return true;
}

// Special subtabs for profile
if ($uri === '/profile/my-courses') {
    $_GET['tab'] = 'my-courses';
    require __DIR__ . '/profile.php';
    return true;
}
if ($uri === '/profile/my-quizzes') {
    $_GET['tab'] = 'my-quizzes';
    require __DIR__ . '/profile.php';
    return true;
}
if ($uri === '/profile/code') {
    $_GET['tab'] = 'code';
    require __DIR__ . '/profile.php';
    return true;
}
if ($uri === '/profile/collection-code') {
    $_GET['tab'] = 'collection-code';
    require __DIR__ . '/profile.php';
    return true;
}

// Match known routes
$trimmed = rtrim($uri, '/');
if (isset($routes[$uri])) {
    require __DIR__ . $routes[$uri];
    return true;
}
if (isset($routes[$trimmed])) {
    require __DIR__ . $routes[$trimmed];
    return true;
}

// Check if file.php exists
if (file_exists(__DIR__ . $uri . '.php')) {
    require __DIR__ . $uri . '.php';
    return true;
}

// Check if admin subpath exists
if (strpos($uri, '/admin/') === 0) {
    $sub = substr($uri, 7);
    if (file_exists(__DIR__ . '/admin/' . $sub . '.php')) {
        require __DIR__ . '/admin/' . $sub . '.php';
        return true;
    }
}

// Default fallback
if (file_exists($file) && is_dir($file) && file_exists($file . '/index.php')) {
    require $file . '/index.php';
    return true;
}

return false;
