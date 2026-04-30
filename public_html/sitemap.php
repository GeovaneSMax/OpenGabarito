<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

echo '  <url><loc>https://opengabarito.com.br/</loc><priority>1.0</priority><changefreq>daily</changefreq></url>' . PHP_EOL;
echo '  <url><loc>https://opengabarito.com.br/gratis.php</loc><priority>0.8</priority></url>' . PHP_EOL;

try {
    $stmt = $pdo->query("SELECT cg.id as cargo_id, c.criado_em FROM cargos cg JOIN concursos c ON cg.concurso_id = c.id WHERE c.deleted_at IS NULL AND cg.deleted_at IS NULL");
    while ($row = $stmt->fetch()) {
        $lastMod = $row['criado_em'] ? date('Y-m-d', strtotime($row['criado_em'])) : date('Y-m-d');
        echo '  <url><loc>https://opengabarito.com.br/ranking.php?cargo_id=' . $row['cargo_id'] . '</loc><lastmod>' . $lastMod . '</lastmod><priority>0.7</priority><changefreq>hourly</changefreq></url>' . PHP_EOL;
    }
} catch (PDOException $e) {}

echo '</urlset>';
