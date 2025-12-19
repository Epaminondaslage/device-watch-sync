<?php
/**
 * Middleware de Autenticação
 * Sistema IoT MQTT
 */

declare(strict_types=1);

class AuthMiddleware
{
    /**
     * Valida o token e retorna dados do usuário
     */
    public static function authenticate(): array
    {
        $token = self::getBearerToken();
        
        if (!$token) {
            throw new Exception('Token de autenticação não fornecido', 401);
        }
        
        $pdo = Database::getConnection();
        
        // Buscar sessão válida
        $stmt = $pdo->prepare("
            SELECT s.*, u.id as user_id, u.nome, u.email, u.perfil
            FROM sessoes s
            INNER JOIN usuarios u ON s.usuario_id = u.id
            WHERE s.token = :token 
            AND s.expires_at > NOW()
            AND u.ativo = 1
        ");
        
        $stmt->execute(['token' => $token]);
        $session = $stmt->fetch();
        
        if (!$session) {
            throw new Exception('Token inválido ou expirado', 401);
        }
        
        return [
            'id' => $session['user_id'],
            'nome' => $session['nome'],
            'email' => $session['email'],
            'perfil' => $session['perfil'],
            'session_id' => $session['id']
        ];
    }
    
    /**
     * Verifica se usuário tem o perfil necessário
     */
    public static function requireRole(array $user, string $role): void
    {
        if ($user['perfil'] !== $role && $user['perfil'] !== 'administrador') {
            throw new Exception('Permissão negada', 403);
        }
    }
    
    /**
     * Extrai o token Bearer do header Authorization
     */
    private static function getBearerToken(): ?string
    {
        $headers = self::getAuthorizationHeader();
        
        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Obtém o header Authorization
     */
    private static function getAuthorizationHeader(): ?string
    {
        if (isset($_SERVER['Authorization'])) {
            return trim($_SERVER['Authorization']);
        }
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }
        
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return trim($headers['Authorization']);
            }
            if (isset($headers['authorization'])) {
                return trim($headers['authorization']);
            }
        }
        
        return null;
    }
}
