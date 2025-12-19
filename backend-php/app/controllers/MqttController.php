<?php
/**
 * Controller de Configuração MQTT
 * Sistema IoT MQTT
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/MqttService.php';

class MqttController
{
    /**
     * Obtém a configuração atual do MQTT
     */
    public static function getConfig(): array
    {
        $pdo = Database::getConnection();
        
        $stmt = $pdo->query("SELECT * FROM mqtt_config ORDER BY id LIMIT 1");
        $config = $stmt->fetch();
        
        if (!$config) {
            return [
                'success' => true,
                'config' => [
                    'host' => 'localhost',
                    'porta' => 1883,
                    'username' => '',
                    'ssl_enabled' => false,
                    'connected' => false,
                    'client_id' => 'iot_server'
                ]
            ];
        }
        
        // Não retornar a senha
        unset($config['password_encrypted']);
        
        return [
            'success' => true,
            'config' => [
                'id' => $config['id'],
                'host' => $config['host'],
                'porta' => (int) $config['porta'],
                'username' => $config['username'] ?? '',
                'ssl_enabled' => (bool) $config['ssl_enabled'],
                'connected' => (bool) $config['connected'],
                'client_id' => $config['client_id'],
                'last_connected' => $config['last_connected']
            ]
        ];
    }
    
    /**
     * Atualiza a configuração do MQTT
     */
    public static function updateConfig(array $data): array
    {
        $pdo = Database::getConnection();
        
        // Validar host
        if (empty($data['host'])) {
            return [
                'success' => false,
                'message' => 'Host é obrigatório'
            ];
        }
        
        // Validar porta
        $porta = (int) ($data['porta'] ?? 1883);
        if ($porta < 1 || $porta > 65535) {
            return [
                'success' => false,
                'message' => 'Porta inválida'
            ];
        }
        
        // Verificar se já existe configuração
        $stmt = $pdo->query("SELECT id FROM mqtt_config LIMIT 1");
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Atualizar
            $sql = "UPDATE mqtt_config SET 
                host = :host,
                porta = :porta,
                username = :username,
                ssl_enabled = :ssl_enabled,
                client_id = :client_id";
            
            $params = [
                'host' => trim($data['host']),
                'porta' => $porta,
                'username' => $data['username'] ?? null,
                'ssl_enabled' => !empty($data['ssl_enabled']) ? 1 : 0,
                'client_id' => $data['client_id'] ?? 'iot_server'
            ];
            
            // Atualizar senha apenas se fornecida
            if (!empty($data['password'])) {
                $sql .= ", password_encrypted = :password";
                $params['password'] = self::encryptPassword($data['password']);
            }
            
            $sql .= " WHERE id = :id";
            $params['id'] = $existing['id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Inserir
            $stmt = $pdo->prepare("
                INSERT INTO mqtt_config (host, porta, username, password_encrypted, ssl_enabled, client_id)
                VALUES (:host, :porta, :username, :password, :ssl_enabled, :client_id)
            ");
            
            $stmt->execute([
                'host' => trim($data['host']),
                'porta' => $porta,
                'username' => $data['username'] ?? null,
                'password' => !empty($data['password']) ? self::encryptPassword($data['password']) : null,
                'ssl_enabled' => !empty($data['ssl_enabled']) ? 1 : 0,
                'client_id' => $data['client_id'] ?? 'iot_server'
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Configuração atualizada com sucesso'
        ];
    }
    
    /**
     * Testa a conexão com o broker MQTT
     */
    public static function testConnection(): array
    {
        $config = self::getConfig()['config'];
        
        $connected = MqttService::testConnection(
            $config['host'],
            $config['porta'],
            $config['username'] ?? null,
            self::getDecryptedPassword()
        );
        
        $pdo = Database::getConnection();
        
        // Atualizar status de conexão
        $stmt = $pdo->prepare("
            UPDATE mqtt_config SET 
                connected = :connected,
                last_connected = IF(:connected = 1, NOW(), last_connected)
        ");
        $stmt->execute(['connected' => $connected ? 1 : 0]);
        
        return [
            'success' => true,
            'connected' => $connected,
            'message' => $connected ? 'Conexão estabelecida com sucesso' : 'Falha ao conectar ao broker'
        ];
    }
    
    /**
     * Obtém logs MQTT
     */
    public static function getLogs(int $limit = 50): array
    {
        $pdo = Database::getConnection();
        
        $limit = min(max($limit, 1), 500);
        
        $stmt = $pdo->prepare("
            SELECT 
                ml.*,
                d.nome as dispositivo_nome
            FROM mqtt_logs ml
            LEFT JOIN dispositivos d ON ml.dispositivo_id = d.id
            ORDER BY ml.recebido_em DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll();
        
        foreach ($logs as &$log) {
            $log['recebido_em'] = date('c', strtotime($log['recebido_em']));
        }
        
        return [
            'success' => true,
            'logs' => $logs,
            'total' => count($logs)
        ];
    }
    
    /**
     * Criptografa a senha do MQTT
     */
    private static function encryptPassword(string $password): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default_key_change_me';
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Descriptografa a senha do MQTT
     */
    private static function getDecryptedPassword(): ?string
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT password_encrypted FROM mqtt_config LIMIT 1");
        $row = $stmt->fetch();
        
        if (!$row || empty($row['password_encrypted'])) {
            return null;
        }
        
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default_key_change_me';
        $data = base64_decode($row['password_encrypted']);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
