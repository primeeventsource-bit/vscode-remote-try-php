<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prime CRM</title>
</head>
<body>
    <div id="root"></div>
    <?php
    $manifestPath = public_path('build/manifest.json');
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (isset($manifest['resources/js/main.jsx']['file'])) {
            $file = $manifest['resources/js/main.jsx']['file'];
            echo '<script type="module" src="/build/' . $file . '"><\/script>';
        }
    }
    ?>
</body>
</html>
