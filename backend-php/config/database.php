<?php
/**
 * Configuração de Conexão com Banco de Dados
 * Sistema IoT MQTT
 */

declare(strict_types=1);

// Carregar variáveis de ambiente
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

/**
 * Classe de configuração do banco de dados
 */
class Database
{
    private static ?PDO $instance = null;
    
    private static string $host;
    private static string $port;
    private static string $database;
    private static string $username;
    private static string $password;
    private static string $charset;
    
    /**
     * Inicializa as configurações
     */
    private static function init(): void
    {
        self::$host = $_ENV['DB_HOST'] ?? 'localhost';
        self::$port = $_ENV['DB_PORT'] ?? '3306';
        self::$database = $_ENV['DB_DATABASE'] ?? 'iot_mqtt_db';
        self::$username = $_ENV['DB_USERNAME'] ?? 'root';
        self::$password = $_ENV['DB_PASSWORD'] ?? '';
        self::$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
    }
    
    /**
     * Retorna instância única da conexão PDO (Singleton)
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::init();
            
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::$host,
                self::$port,
                self::$database,
                self::$charset
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            try {
                self::$instance = new PDO($dsn, self::$username, self::$password, $options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Erro de conexão com o banco de dados");
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Fecha a conexão
     */
    public static function close(): void
    {
        self::$instance = null;
    }
    
    /**
     * Testa a conexão com o banco
     */
    public static function testConnection(): bool
    {
        try {
            $pdo = self::getConnection();
            $pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
