<?php
/**
 * Configuração de Conexão com o Banco de Dados
 */
require_once __DIR__ . '/env_loader.php';

$host = env('DB_HOST') ?: '127.0.0.1'; 
$dbname = env('DB_NAME') ?: 'sql_opengabarito_com_br';
$username = env('DB_USER') ?: 'root';
$password = env('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Em produção, não exiba detalhes do erro para o usuário final
    if (env('APP_ENV') === 'prod') {
        die("Erro na conexão com o banco de dados.");
    } else {
        die("Erro na conexão com o banco de dados: " . $e->getMessage());
    }
}
?>