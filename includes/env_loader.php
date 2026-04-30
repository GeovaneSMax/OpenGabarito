<?php
/**
 * Simple .env Loader
 * Carrega variáveis de ambiente de um arquivo .env para o ambiente PHP.
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            if (function_exists('putenv')) {
                @putenv(sprintf('%s=%s', $name, $value));
            }
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

/**
 * Recupera uma variável de ambiente de forma segura
 */
function env($key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? false;
    if ($value === false && function_exists('getenv')) {
        $value = getenv($key);
    }
    return $value !== false ? $value : $default;
}

// Carregar o .env se existir na raiz
loadEnv(__DIR__ . '/../.env');
