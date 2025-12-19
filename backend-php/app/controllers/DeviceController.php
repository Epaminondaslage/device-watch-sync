<?php
/**
 * Controller de Dispositivos
 * Sistema IoT MQTT
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/MqttService.php';

class DeviceController
{
    /**
     * Lista todos os dispositivos
     */
    public static function list(array $filters = []): array
    {
        $pdo = Database::getConnection();
        
        $sql = "SELECT * FROM dispositivos WHERE 1=1";
        $params = [];
        
        // Filtro por busca (nome, MAC, IP)
        if (!empty($filters['search'])) {
            $sql .= " AND (nome LIKE :search OR mac_address LIKE :search OR ip LIKE :search OR mqtt_id LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Filtro por status
        if (!empty($filters['status']) && in_array($filters['status'], ['online', 'offline', 'unknown'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }
        
        $sql .= " ORDER BY nome ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $devices = $stmt->fetchAll();
        
        // Formatar datas
        foreach ($devices as &$device) {
            $device['last_seen'] = $device['last_seen'] ? date('c', strtotime($device['last_seen'])) : null;
            $device['created_at'] = date('c', strtotime($device['created_at']));
            $device['last_payload'] = $device['last_payload'] ? json_decode($device['last_payload'], true) : null;
        }
        
        return [
            'success' => true,
            'devices' => $devices,
            'total' => count($devices)
        ];
    }
    
    /**
     * Obtém um dispositivo específico
     */
    public static function get(int $id): array
    {
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM dispositivos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        $device = $stmt->fetch();
        
        if (!$device) {
            return [
                'success' => false,
                'message' => 'Dispositivo não encontrado'
            ];
        }
        
        $device['last_seen'] = $device['last_seen'] ? date('c', strtotime($device['last_seen'])) : null;
        $device['created_at'] = date('c', strtotime($device['created_at']));
        $device['last_payload'] = $device['last_payload'] ? json_decode($device['last_payload'], true) : null;
        
        return [
            'success' => true,
            'device' => $device
        ];
    }
    
    /**
     * Cria um novo dispositivo
     */
    public static function create(array $data): array
    {
        $required = ['nome', 'mac_address', 'mqtt_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "Campo '$field' é obrigatório"
                ];
            }
        }
        
        // Validar MAC Address
        if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $data['mac_address'])) {
            return [
                'success' => false,
                'message' => 'MAC Address inválido (formato: XX:XX:XX:XX:XX:XX)'
            ];
        }
        
        $pdo = Database::getConnection();
        
        // Verificar duplicidade
        $stmt = $pdo->prepare("SELECT id FROM dispositivos WHERE mac_address = :mac OR mqtt_id = :mqtt_id");
        $stmt->execute([
            'mac' => strtoupper($data['mac_address']),
            'mqtt_id' => $data['mqtt_id']
        ]);
        
        if ($stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'MAC Address ou MQTT ID já cadastrado'
            ];
        }
        
        // Inserir dispositivo
        $stmt = $pdo->prepare("
            INSERT INTO dispositivos (nome, mac_address, mqtt_id, ip, status)
            VALUES (:nome, :mac_address, :mqtt_id, :ip, 'unknown')
        ");
        
        $stmt->execute([
            'nome' => trim($data['nome']),
            'mac_address' => strtoupper(trim($data['mac_address'])),
            'mqtt_id' => trim($data['mqtt_id']),
            'ip' => !empty($data['ip']) ? trim($data['ip']) : null
        ]);
        
        $id = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Dispositivo criado com sucesso',
            'id' => $id
        ];
    }
    
    /**
     * Atualiza um dispositivo
     */
    public static function update(int $id, array $data): array
    {
        $pdo = Database::getConnection();
        
        // Verificar se existe
        $stmt = $pdo->prepare("SELECT id FROM dispositivos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        if (!$stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Dispositivo não encontrado'
            ];
        }
        
        // Campos permitidos para atualização
        $allowedFields = ['nome', 'mac_address', 'mqtt_id', 'ip'];
        $updates = [];
        $params = ['id' => $id];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[$field] = trim($data[$field]);
            }
        }
        
        if (empty($updates)) {
            return [
                'success' => false,
                'message' => 'Nenhum campo para atualizar'
            ];
        }
        
        // Validar MAC se fornecido
        if (isset($data['mac_address']) && !preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $data['mac_address'])) {
            return [
                'success' => false,
                'message' => 'MAC Address inválido'
            ];
        }
        
        $sql = "UPDATE dispositivos SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return [
            'success' => true,
            'message' => 'Dispositivo atualizado com sucesso'
        ];
    }
    
    /**
     * Remove um dispositivo
     */
    public static function delete(int $id): array
    {
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("DELETE FROM dispositivos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        if ($stmt->rowCount() === 0) {
            return [
                'success' => false,
                'message' => 'Dispositivo não encontrado'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Dispositivo removido com sucesso'
        ];
    }
    
    /**
     * Liga/Desliga um dispositivo
     */
    public static function togglePower(int $id, string $power, array $user): array
    {
        $power = strtoupper($power);
        
        if (!in_array($power, ['ON', 'OFF'])) {
            return [
                'success' => false,
                'message' => 'Comando inválido. Use ON ou OFF'
            ];
        }
        
        $pdo = Database::getConnection();
        
        // Buscar dispositivo
        $stmt = $pdo->prepare("SELECT * FROM dispositivos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $device = $stmt->fetch();
        
        if (!$device) {
            return [
                'success' => false,
                'message' => 'Dispositivo não encontrado'
            ];
        }
        
        // Publicar comando MQTT
        $topic = "cmnd/{$device['mqtt_id']}/POWER";
        $success = MqttService::publish($topic, $power);
        
        // Registrar comando no log
        $stmt = $pdo->prepare("
            INSERT INTO command_logs (dispositivo_id, usuario_id, comando, sucesso, mensagem)
            VALUES (:dispositivo_id, :usuario_id, :comando, :sucesso, :mensagem)
        ");
        
        $stmt->execute([
            'dispositivo_id' => $id,
            'usuario_id' => $user['id'],
            'comando' => $power,
            'sucesso' => $success ? 1 : 0,
            'mensagem' => $success ? 'Comando enviado' : 'Falha ao enviar comando'
        ]);
        
        // Registrar no MQTT log
        $stmt = $pdo->prepare("
            INSERT INTO mqtt_logs (dispositivo_id, topico, payload, tipo, direcao)
            VALUES (:dispositivo_id, :topico, :payload, 'COMMAND', 'OUT')
        ");
        
        $stmt->execute([
            'dispositivo_id' => $id,
            'topico' => $topic,
            'payload' => $power
        ]);
        
        return [
            'success' => $success,
            'message' => $success ? "Comando $power enviado para {$device['nome']}" : 'Falha ao enviar comando'
        ];
    }
}
