<?php
/**
 * Controller do Dashboard
 * Sistema IoT MQTT
 */

declare(strict_types=1);

class DashboardController
{
    /**
     * Obtém estatísticas do dashboard
     */
    public static function getStats(): array
    {
        $pdo = Database::getConnection();
        
        // Usar a view criada no schema
        $stmt = $pdo->query("SELECT * FROM vw_dashboard_stats");
        $stats = $stmt->fetch();
        
        // Buscar status da conexão MQTT
        $mqttStmt = $pdo->query("SELECT connected, last_connected FROM mqtt_config LIMIT 1");
        $mqtt = $mqttStmt->fetch();
        
        return [
            'success' => true,
            'stats' => [
                'total_dispositivos' => (int) ($stats['total_dispositivos'] ?? 0),
                'dispositivos_online' => (int) ($stats['dispositivos_online'] ?? 0),
                'dispositivos_offline' => (int) ($stats['dispositivos_offline'] ?? 0),
                'dispositivos_unknown' => (int) ($stats['dispositivos_unknown'] ?? 0),
                'ultima_atualizacao' => $stats['ultima_atualizacao'] 
                    ? date('c', strtotime($stats['ultima_atualizacao'])) 
                    : null,
                'mqtt_connected' => (bool) ($mqtt['connected'] ?? false),
                'mqtt_last_connected' => $mqtt['last_connected'] 
                    ? date('c', strtotime($mqtt['last_connected'])) 
                    : null
            ]
        ];
    }
    
    /**
     * Obtém histórico de comandos
     */
    public static function getCommandHistory(int $limit = 100): array
    {
        $pdo = Database::getConnection();
        
        $limit = min(max($limit, 1), 500);
        
        $stmt = $pdo->prepare("
            SELECT * FROM vw_command_history
            LIMIT :limit
        ");
        
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $commands = $stmt->fetchAll();
        
        foreach ($commands as &$cmd) {
            $cmd['executado_em'] = date('c', strtotime($cmd['executado_em']));
        }
        
        return [
            'success' => true,
            'commands' => $commands,
            'total' => count($commands)
        ];
    }
    
    /**
     * Obtém dispositivos recentes (últimos 5 online)
     */
    public static function getRecentDevices(): array
    {
        $pdo = Database::getConnection();
        
        $stmt = $pdo->query("
            SELECT id, nome, status, last_seen, last_payload
            FROM dispositivos
            WHERE last_seen IS NOT NULL
            ORDER BY last_seen DESC
            LIMIT 5
        ");
        
        $devices = $stmt->fetchAll();
        
        foreach ($devices as &$device) {
            $device['last_seen'] = $device['last_seen'] 
                ? date('c', strtotime($device['last_seen'])) 
                : null;
            $device['last_payload'] = $device['last_payload'] 
                ? json_decode($device['last_payload'], true) 
                : null;
        }
        
        return [
            'success' => true,
            'devices' => $devices
        ];
    }
}
