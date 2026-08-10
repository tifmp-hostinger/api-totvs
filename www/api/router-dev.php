<?php
// Router para o servidor embutido do PHP em desenvolvimento:
//   php -S 127.0.0.1:8099 router-dev.php
// Serve arquivos existentes de public/ (admin.html, docs.html...) e envia o
// resto para o Slim. Em produção o Apache faz isso via public/.htaccess.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/public' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/public/index.php';
