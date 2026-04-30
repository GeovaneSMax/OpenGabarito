<?php
// Configurações do Sistema
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/rate_limit.php';

define('GROQ_API_KEY', env('GROQ_API_KEY') ?: '');

// Lista de modelos para failover automático
define('GROQ_MODELS', [
    'llama-3.3-70b-versatile',
    'llama-3.1-70b-versatile',
    'mixtral-8x7b-32768',
    'llama-3.1-8b-instant'
]);

// Cloudflare Turnstile
define('TURNSTILE_SITE_KEY', env('TURNSTILE_SITE_KEY') ?: '');
define('TURNSTILE_SECRET_KEY', env('TURNSTILE_SECRET_KEY') ?: '');

// Gemini API (Google AI Studio)
define('GEMINI_API_KEY', env('GEMINI_API_KEY') ?: '');

// Evolution API / n8n Webhook
define('EVOLUTION_API_KEY', env('EVOLUTION_API_KEY') ?: '');
define('EVOLUTION_URL', env('EVOLUTION_URL') ?: '');
?>
