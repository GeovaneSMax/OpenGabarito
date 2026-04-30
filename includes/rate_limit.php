<?php
/**
 * Verifica se o IP atingiu o limite de requisições para evitar abusos.
 */
function checkRateLimit($action = 'ai_request', $limit = 10, $period = 3600) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    // Garante que o diretório cache existe
    $cache_dir = __DIR__ . "/../cache";
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0775, true);
    }
    
    $cache_file = $cache_dir . "/rate_limit_" . md5($ip . $action) . ".json";
    
    $now = time();
    $data = ['count' => 0, 'first_request' => $now];
    
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        
        // Se o período expirou, reseta o contador
        if ($now - $data['first_request'] > $period) {
            $data = ['count' => 0, 'first_request' => $now];
        }
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $data['count']++;
    file_put_contents($cache_file, json_encode($data));
    return true;
}
?>
