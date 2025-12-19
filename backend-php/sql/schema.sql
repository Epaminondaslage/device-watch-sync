-- =====================================================
-- Sistema IoT MQTT - Schema do Banco de Dados
-- Compatível com MySQL 5.7+ / MariaDB 10.3+
-- =====================================================

-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS iot_mqtt_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE iot_mqtt_db;

-- =====================================================
-- Tabela: usuarios
-- =====================================================
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('administrador', 'operador') NOT NULL DEFAULT 'operador',
  ativo BOOLEAN NOT NULL DEFAULT TRUE,
  ultimo_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_email (email),
  INDEX idx_perfil (perfil),
  INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: dispositivos
-- =====================================================
CREATE TABLE IF NOT EXISTS dispositivos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  mac_address VARCHAR(17) NOT NULL UNIQUE,
  mqtt_id VARCHAR(100) NOT NULL UNIQUE,
  ip VARCHAR(45) NULL,
  status ENUM('online', 'offline', 'unknown') NOT NULL DEFAULT 'unknown',
  last_seen DATETIME NULL,
  last_payload JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_mqtt_id (mqtt_id),
  INDEX idx_status (status),
  INDEX idx_mac (mac_address),
  INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: mqtt_config
-- =====================================================
CREATE TABLE IF NOT EXISTS mqtt_config (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  host VARCHAR(255) NOT NULL DEFAULT 'localhost',
  porta INT UNSIGNED NOT NULL DEFAULT 1883,
  username VARCHAR(100) NULL,
  password_encrypted VARCHAR(255) NULL,
  client_id VARCHAR(100) NOT NULL DEFAULT 'iot_server',
  keepalive INT UNSIGNED NOT NULL DEFAULT 60,
  ssl_enabled BOOLEAN NOT NULL DEFAULT FALSE,
  connected BOOLEAN NOT NULL DEFAULT FALSE,
  last_connected DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: mqtt_logs
-- =====================================================
CREATE TABLE IF NOT EXISTS mqtt_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispositivo_id INT UNSIGNED NULL,
  topico VARCHAR(255) NOT NULL,
  payload TEXT NULL,
  tipo ENUM('LWT', 'STATE', 'COMMAND', 'OTHER') NOT NULL DEFAULT 'OTHER',
  direcao ENUM('IN', 'OUT') NOT NULL DEFAULT 'IN',
  recebido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_dispositivo (dispositivo_id),
  INDEX idx_topico (topico),
  INDEX idx_tipo (tipo),
  INDEX idx_recebido_em (recebido_em),
  
  CONSTRAINT fk_mqtt_logs_dispositivo
    FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: command_logs (Histórico de comandos)
-- =====================================================
CREATE TABLE IF NOT EXISTS command_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispositivo_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  comando ENUM('ON', 'OFF') NOT NULL,
  sucesso BOOLEAN NOT NULL DEFAULT FALSE,
  mensagem VARCHAR(255) NULL,
  executado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_dispositivo (dispositivo_id),
  INDEX idx_usuario (usuario_id),
  INDEX idx_executado_em (executado_em),
  
  CONSTRAINT fk_command_logs_dispositivo
    FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_command_logs_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabela: sessoes (Tokens de autenticação)
-- =====================================================
CREATE TABLE IF NOT EXISTS sessoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_token (token),
  INDEX idx_usuario (usuario_id),
  INDEX idx_expires (expires_at),
  
  CONSTRAINT fk_sessoes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Dados Iniciais
-- =====================================================

-- Usuário administrador padrão (senha: admin123)
INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES
('Administrador', 'admin@sistema.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador');

-- Configuração MQTT padrão
INSERT INTO mqtt_config (host, porta, client_id) VALUES
('localhost', 1883, 'iot_server_01');

-- =====================================================
-- Views úteis
-- =====================================================

-- View: Estatísticas do Dashboard
CREATE OR REPLACE VIEW vw_dashboard_stats AS
SELECT 
  COUNT(*) AS total_dispositivos,
  SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END) AS dispositivos_online,
  SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) AS dispositivos_offline,
  SUM(CASE WHEN status = 'unknown' THEN 1 ELSE 0 END) AS dispositivos_unknown,
  MAX(last_seen) AS ultima_atualizacao
FROM dispositivos;

-- View: Últimos comandos com detalhes
CREATE OR REPLACE VIEW vw_command_history AS
SELECT 
  cl.id,
  cl.comando,
  cl.sucesso,
  cl.mensagem,
  cl.executado_em,
  d.nome AS dispositivo_nome,
  d.mqtt_id,
  u.nome AS usuario_nome
FROM command_logs cl
LEFT JOIN dispositivos d ON cl.dispositivo_id = d.id
LEFT JOIN usuarios u ON cl.usuario_id = u.id
ORDER BY cl.executado_em DESC;

-- =====================================================
-- Stored Procedures
-- =====================================================

DELIMITER //

-- Procedure: Atualizar status do dispositivo via LWT
CREATE PROCEDURE sp_update_device_status(
  IN p_mqtt_id VARCHAR(100),
  IN p_status ENUM('online', 'offline', 'unknown'),
  IN p_payload TEXT
)
BEGIN
  UPDATE dispositivos 
  SET 
    status = p_status,
    last_seen = NOW(),
    last_payload = IF(p_payload IS NOT NULL, JSON_OBJECT('data', p_payload), last_payload)
  WHERE mqtt_id = p_mqtt_id;
  
  -- Log da atualização
  INSERT INTO mqtt_logs (dispositivo_id, topico, payload, tipo, direcao)
  SELECT id, CONCAT('tele/', mqtt_id, '/LWT'), p_payload, 'LWT', 'IN'
  FROM dispositivos WHERE mqtt_id = p_mqtt_id;
END //

-- Procedure: Registrar comando enviado
CREATE PROCEDURE sp_log_command(
  IN p_dispositivo_id INT UNSIGNED,
  IN p_usuario_id INT UNSIGNED,
  IN p_comando ENUM('ON', 'OFF'),
  IN p_sucesso BOOLEAN,
  IN p_mensagem VARCHAR(255)
)
BEGIN
  INSERT INTO command_logs (dispositivo_id, usuario_id, comando, sucesso, mensagem)
  VALUES (p_dispositivo_id, p_usuario_id, p_comando, p_sucesso, p_mensagem);
END //

-- Procedure: Limpar logs antigos (manter últimos 30 dias)
CREATE PROCEDURE sp_cleanup_old_logs()
BEGIN
  DELETE FROM mqtt_logs WHERE recebido_em < DATE_SUB(NOW(), INTERVAL 30 DAY);
  DELETE FROM command_logs WHERE executado_em < DATE_SUB(NOW(), INTERVAL 90 DAY);
  DELETE FROM sessoes WHERE expires_at < NOW();
END //

DELIMITER ;

-- =====================================================
-- Event Scheduler (limpeza automática)
-- =====================================================
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS evt_cleanup_logs
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO CALL sp_cleanup_old_logs();
