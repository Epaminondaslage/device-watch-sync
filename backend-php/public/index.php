<?php
/**
 * Entry Point da API
 * Sistema IoT MQTT
 */

declare(strict_types=1);

// Configurações de erro baseadas no ambiente
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Autoload
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';
require_once dirname(__DIR__) . '/app/controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/controllers/DeviceController.php';
require_once dirname(__DIR__) . '/app/controllers/MqttController.php';
require_once dirname(__DIR__) . '/app/controllers/DashboardController.php';

// Headers CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS - Origens permitidas
$allowedOrigins = explode(',', $_ENV['CORS_ORIGINS'] ?? '*');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins) || $allowedOrigins[0] === '*') {
    header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Resposta JSON padronizada
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Parsear corpo da requisição
 */
function getRequestBody(): array
{
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

// Obter URI e método
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api', '', $uri); // Remove prefixo /api se houver
$method = $_SERVER['REQUEST_METHOD'];

// Rotas da API
try {
    // ==========================================
    // Rotas de Autenticação (públicas)
    // ==========================================
    
    if ($uri === '/auth/login' && $method === 'POST') {
        $body = getRequestBody();
        $result = AuthController::login($body['email'] ?? '', $body['password'] ?? '');
        jsonResponse($result, $result['success'] ? 200 : 401);
    }
    
    // ==========================================
    // Middleware de Autenticação
    // ==========================================
    
    $user = AuthMiddleware::authenticate();
    
    // ==========================================
    // Rotas de Autenticação (protegidas)
    // ==========================================
    
    if ($uri === '/auth/logout' && $method === 'POST') {
        $result = AuthController::logout($user);
        jsonResponse($result);
    }
    
    if ($uri === '/auth/me' && $method === 'GET') {
        jsonResponse(['success' => true, 'user' => $user]);
    }
    
    // ==========================================
    // Rotas de Dashboard
    // ==========================================
    
    if ($uri === '/dashboard/stats' && $method === 'GET') {
        $result = DashboardController::getStats();
        jsonResponse($result);
    }
    
    // ==========================================
    // Rotas de Dispositivos
    // ==========================================
    
    if (preg_match('/^\/devices\/?$/', $uri)) {
        if ($method === 'GET') {
            $filters = [
                'search' => $_GET['search'] ?? null,
                'status' => $_GET['status'] ?? null,
            ];
            $result = DeviceController::list($filters);
            jsonResponse($result);
        }
        
        if ($method === 'POST') {
            AuthMiddleware::requireRole($user, 'administrador');
            $body = getRequestBody();
            $result = DeviceController::create($body);
            jsonResponse($result, $result['success'] ? 201 : 400);
        }
    }
    
    if (preg_match('/^\/devices\/(\d+)$/', $uri, $matches)) {
        $deviceId = (int) $matches[1];
        
        if ($method === 'GET') {
            $result = DeviceController::get($deviceId);
            jsonResponse($result, $result['success'] ? 200 : 404);
        }
        
        if ($method === 'PUT') {
            AuthMiddleware::requireRole($user, 'administrador');
            $body = getRequestBody();
            $result = DeviceController::update($deviceId, $body);
            jsonResponse($result, $result['success'] ? 200 : 400);
        }
        
        if ($method === 'DELETE') {
            AuthMiddleware::requireRole($user, 'administrador');
            $result = DeviceController::delete($deviceId);
            jsonResponse($result, $result['success'] ? 200 : 404);
        }
    }
    
    if (preg_match('/^\/devices\/(\d+)\/power$/', $uri, $matches)) {
        $deviceId = (int) $matches[1];
        
        if ($method === 'POST') {
            $body = getRequestBody();
            $result = DeviceController::togglePower($deviceId, $body['power'] ?? 'ON', $user);
            jsonResponse($result, $result['success'] ? 200 : 400);
        }
    }
    
    // ==========================================
    // Rotas de MQTT
    // ==========================================
    
    if ($uri === '/mqtt/config' && $method === 'GET') {
        $result = MqttController::getConfig();
        jsonResponse($result);
    }
    
    if ($uri === '/mqtt/config' && $method === 'PUT') {
        AuthMiddleware::requireRole($user, 'administrador');
        $body = getRequestBody();
        $result = MqttController::updateConfig($body);
        jsonResponse($result, $result['success'] ? 200 : 400);
    }
    
    if ($uri === '/mqtt/test' && $method === 'POST') {
        AuthMiddleware::requireRole($user, 'administrador');
        $result = MqttController::testConnection();
        jsonResponse($result);
    }
    
    if ($uri === '/mqtt/logs' && $method === 'GET') {
        $limit = (int) ($_GET['limit'] ?? 50);
        $result = MqttController::getLogs($limit);
        jsonResponse($result);
    }
    
    // ==========================================
    // Rotas de Histórico de Comandos
    // ==========================================
    
    if ($uri === '/commands/history' && $method === 'GET') {
        $limit = (int) ($_GET['limit'] ?? 100);
        $result = DashboardController::getCommandHistory($limit);
        jsonResponse($result);
    }
    
    // ==========================================
    // Rota não encontrada
    // ==========================================
    
    jsonResponse([
        'success' => false,
        'message' => 'Endpoint não encontrado',
        'path' => $uri
    ], 404);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    
    $status = $e->getCode() ?: 500;
    if ($status < 100 || $status > 599) $status = 500;
    
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], $status);
}
