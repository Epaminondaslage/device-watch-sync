<?php
/**
 * Controller de Autenticação
 * Sistema IoT MQTT
 */

declare(strict_types=1);

class AuthController
{
    /**
     * Realiza o login do usuário
     */
    public static function login(string $email, string $password): array
    {
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Email e senha são obrigatórios'
            ];
        }
        
        $pdo = Database::getConnection();
        
        // Buscar usuário
        $stmt = $pdo->prepare("
            SELECT id, nome, email, senha_hash, perfil, ativo
            FROM usuarios 
            WHERE email = :email
        ");
        
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Credenciais inválidas'
            ];
        }
        
        if (!$user['ativo']) {
            return [
                'success' => false,
                'message' => 'Usuário desativado'
            ];
        }
        
        // Verificar senha
        if (!password_verify($password, $user['senha_hash'])) {
            return [
                'success' => false,
                'message' => 'Credenciais inválidas'
            ];
        }
        
        // Gerar token de sessão
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Criar sessão
        $stmt = $pdo->prepare("
            INSERT INTO sessoes (usuario_id, token, ip_address, user_agent, expires_at)
            VALUES (:usuario_id, :token, :ip, :user_agent, :expires_at)
        ");
        
        $stmt->execute([
            'usuario_id' => $user['id'],
            'token' => $token,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => $expiresAt
        ]);
        
        // Atualizar último login
        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id");
        $stmt->execute(['id' => $user['id']]);
        
        return [
            'success' => true,
            'message' => 'Login realizado com sucesso',
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => [
                'id' => $user['id'],
                'nome' => $user['nome'],
                'email' => $user['email'],
                'perfil' => $user['perfil']
            ]
        ];
    }
    
    /**
     * Realiza o logout do usuário
     */
    public static function logout(array $user): array
    {
        $pdo = Database::getConnection();
        
        // Invalidar sessão
        $stmt = $pdo->prepare("DELETE FROM sessoes WHERE id = :session_id");
        $stmt->execute(['session_id' => $user['session_id']]);
        
        return [
            'success' => true,
            'message' => 'Logout realizado com sucesso'
        ];
    }
    
    /**
     * Cria um novo usuário (apenas admin)
     */
    public static function createUser(array $data): array
    {
        $required = ['nome', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "Campo '$field' é obrigatório"
                ];
            }
        }
        
        $pdo = Database::getConnection();
        
        // Verificar email duplicado
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
        $stmt->execute(['email' => strtolower(trim($data['email']))]);
        
        if ($stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Email já cadastrado'
            ];
        }
        
        // Criar usuário
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nome, email, senha_hash, perfil)
            VALUES (:nome, :email, :senha_hash, :perfil)
        ");
        
        $stmt->execute([
            'nome' => trim($data['nome']),
            'email' => strtolower(trim($data['email'])),
            'senha_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'perfil' => $data['perfil'] ?? 'operador'
        ]);
        
        return [
            'success' => true,
            'message' => 'Usuário criado com sucesso',
            'id' => $pdo->lastInsertId()
        ];
    }
}
