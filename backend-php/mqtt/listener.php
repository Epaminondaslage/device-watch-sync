#!/usr/bin/env php
<?php
/**
 * MQTT Listener - Script CLI
 * Sistema IoT MQTT
 * 
 * Este script roda em background e escuta mensagens do broker MQTT,
 * atualizando o banco de dados automaticamente.
 * 
 * Uso:
 *   php listener.php
 * 
 * Para rodar em background:
 *   nohup php listener.php > /dev/null 2>&1 &
 *   ou via systemd (veja iot-mqtt.service)
 * 
 * Requisitos:
 *   composer require php-mqtt/client
 */

declare(strict_types=1);

// Verificar se está rodando via CLI
if (php_sapi_name() !== 'cli') {
    die("Este script deve ser executado via linha de comando.\n");
}

// Autoload
require_once dirname(__DIR__) . '/config/database.php';

// Configurações
$reconnectDelay = 5; // segundos
$keepalive = 60;

/**
 * Log com timestamp
 */
function logMessage(string $message): void
{
    $logPath = $_ENV['LOG_PATH'] ?? dirname(__DIR__) . '/logs';
    $logFile = $logPath . '/mqtt_listener.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message";
    
    echo $line . PHP_EOL;
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

/**
 * Obtém configuração do MQTT do banco
 */
function getMqttConfig(): ?array
{
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM mqtt_config LIMIT 1");
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        logMessage("Erro ao obter configuração: " . $e->getMessage());
        return null;
    }
}

/**
 * Atualiza status do dispositivo
 */
function updateDeviceStatus(string $mqttId, string $status, ?string $payload = null): void
{
    try {
        $pdo = Database::getConnection();
        
        // Buscar dispositivo
        $stmt = $pdo->prepare("SELECT id FROM dispositivos WHERE mqtt_id = :mqtt_id");
        $stmt->execute(['mqtt_id' => $mqttId]);
        $device = $stmt->fetch();
        
        if (!$device) {
            logMessage("Dispositivo não encontrado: $mqttId");
            return;
        }
        
        // Atualizar status
        $stmt = $pdo->prepare("
            UPDATE dispositivos SET 
                status = :status,
                last_seen = NOW(),
                last_payload = IF(:payload IS NOT NULL, :payload, last_payload)
            WHERE mqtt_id = :mqtt_id
        ");
        
        $stmt->execute([
            'status' => $status,
            'payload' => $payload,
            'mqtt_id' => $mqttId
        ]);
        
        logMessage("Status atualizado: $mqttId -> $status");
        
    } catch (Exception $e) {
        logMessage("Erro ao atualizar status: " . $e->getMessage());
    }
}

/**
 * Registra log MQTT no banco
 */
function logMqttMessage(string $topic, string $payload, string $tipo = 'OTHER'): void
{
    try {
        $pdo = Database::getConnection();
        
        // Extrair mqtt_id do tópico (ex: tele/dispositivo/LWT)
        $parts = explode('/', $topic);
        $mqttId = $parts[1] ?? null;
        
        // Buscar dispositivo
        $deviceId = null;
        if ($mqttId) {
            $stmt = $pdo->prepare("SELECT id FROM dispositivos WHERE mqtt_id = :mqtt_id");
            $stmt->execute(['mqtt_id' => $mqttId]);
            $device = $stmt->fetch();
            $deviceId = $device['id'] ?? null;
        }
        
        // Inserir log
        $stmt = $pdo->prepare("
            INSERT INTO mqtt_logs (dispositivo_id, topico, payload, tipo, direcao)
            VALUES (:dispositivo_id, :topico, :payload, :tipo, 'IN')
        ");
        
        $stmt->execute([
            'dispositivo_id' => $deviceId,
            'topico' => $topic,
            'payload' => $payload,
            'tipo' => $tipo
        ]);
        
    } catch (Exception $e) {
        logMessage("Erro ao registrar log: " . $e->getMessage());
    }
}

/**
 * Processa mensagem recebida
 */
function processMessage(string $topic, string $message): void
{
    logMessage("Recebido: $topic -> $message");
    
    // Extrair partes do tópico
    $parts = explode('/', $topic);
    
    if (count($parts) < 3) {
        logMessage("Tópico inválido: $topic");
        return;
    }
    
    $prefix = $parts[0]; // tele, cmnd, stat
    $mqttId = $parts[1]; // identificador do dispositivo
    $type = $parts[2];   // LWT, STATE, POWER, etc.
    
    // Processar por tipo
    switch ($type) {
        case 'LWT':
            // Last Will and Testament - indica online/offline
            $status = strtolower($message) === 'online' ? 'online' : 'offline';
            updateDeviceStatus($mqttId, $status);
            logMqttMessage($topic, $message, 'LWT');
            break;
            
        case 'STATE':
            // Estado completo do dispositivo (JSON)
            updateDeviceStatus($mqttId, 'online', $message);
            logMqttMessage($topic, $message, 'STATE');
            break;
            
        case 'RESULT':
        case 'POWER':
        case 'POWER1':
        case 'POWER2':
            // Resultado de comando
            updateDeviceStatus($mqttId, 'online', $message);
            logMqttMessage($topic, $message, 'STATE');
            break;
            
        default:
            // Outros tópicos
            logMqttMessage($topic, $message, 'OTHER');
            break;
    }
}

/**
 * Loop principal do listener
 */
function runListener(): void
{
    global $reconnectDelay, $keepalive;
    
    logMessage("=== MQTT Listener Iniciado ===");
    
    while (true) {
        $config = getMqttConfig();
        
        if (!$config) {
            logMessage("Configuração MQTT não encontrada. Aguardando...");
            sleep($reconnectDelay);
            continue;
        }
        
        logMessage("Conectando a {$config['host']}:{$config['porta']}...");
        
        // =====================================================
        // IMPLEMENTAÇÃO COM php-mqtt/client
        // =====================================================
        
        /*
        // Descomente após instalar: composer require php-mqtt/client
        
        try {
            $mqtt = new \PhpMqtt\Client\MqttClient(
                $config['host'],
                (int) $config['porta'],
                $config['client_id'] . '_listener'
            );
            
            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
                ->setUsername($config['username'] ?: null)
                ->setPassword($config['password_encrypted'] ? decryptPassword($config['password_encrypted']) : null)
                ->setKeepAliveInterval($keepalive)
                ->setConnectTimeout(10)
                ->setUseTls((bool) $config['ssl_enabled']);
            
            $mqtt->connect($connectionSettings, true);
            
            logMessage("Conectado ao broker MQTT!");
            
            // Atualizar status no banco
            $pdo = Database::getConnection();
            $pdo->exec("UPDATE mqtt_config SET connected = 1, last_connected = NOW()");
            
            // Assinar tópicos Tasmota
            $topics = [
                'tele/+/LWT',      // Last Will and Testament
                'tele/+/STATE',    // Estado do dispositivo
                'stat/+/RESULT',   // Resultado de comandos
                'stat/+/POWER',    // Estado de energia
                'stat/+/POWER1',
                'stat/+/POWER2',
            ];
            
            foreach ($topics as $topic) {
                $mqtt->subscribe($topic, function ($topic, $message) {
                    processMessage($topic, $message);
                }, 0);
                logMessage("Inscrito em: $topic");
            }
            
            // Loop de escuta
            $mqtt->loop(true);
            
        } catch (\PhpMqtt\Client\Exceptions\MqttClientException $e) {
            logMessage("Erro MQTT: " . $e->getMessage());
            
            // Atualizar status no banco
            try {
                $pdo = Database::getConnection();
                $pdo->exec("UPDATE mqtt_config SET connected = 0");
            } catch (Exception $e) {}
            
            logMessage("Reconectando em {$reconnectDelay} segundos...");
            sleep($reconnectDelay);
        }
        */
        
        // =====================================================
        // SIMULAÇÃO (remova quando usar php-mqtt/client)
        // =====================================================
        
        logMessage("MODO SIMULAÇÃO - Instale php-mqtt/client para funcionalidade real");
        logMessage("Execute: composer require php-mqtt/client");
        logMessage("Aguardando 30 segundos...");
        
        // Simular check de conexão
        $socket = @fsockopen($config['host'], (int) $config['porta'], $errno, $errstr, 5);
        if ($socket) {
            fclose($socket);
            logMessage("Broker acessível em {$config['host']}:{$config['porta']}");
            
            try {
                $pdo = Database::getConnection();
                $pdo->exec("UPDATE mqtt_config SET connected = 1, last_connected = NOW()");
            } catch (Exception $e) {}
        } else {
            logMessage("Broker não acessível: $errstr");
            
            try {
                $pdo = Database::getConnection();
                $pdo->exec("UPDATE mqtt_config SET connected = 0");
            } catch (Exception $e) {}
        }
        
        sleep(30);
    }
}

/**
 * Descriptografa senha
 */
function decryptPassword(?string $encrypted): ?string
{
    if (empty($encrypted)) {
        return null;
    }
    
    $key = $_ENV['ENCRYPTION_KEY'] ?? 'default_key_change_me';
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encryptedData = substr($data, 16);
    
    return openssl_decrypt($encryptedData, 'AES-256-CBC', $key, 0, $iv);
}

// Capturar sinais de término
pcntl_async_signals(true);
pcntl_signal(SIGTERM, function () {
    logMessage("Recebido SIGTERM. Encerrando...");
    exit(0);
});
pcntl_signal(SIGINT, function () {
    logMessage("Recebido SIGINT. Encerrando...");
    exit(0);
});

// Iniciar listener
runListener();
