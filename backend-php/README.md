# Backend PHP/MySQL - Sistema IoT MQTT

## Estrutura do Projeto

```
backend-php/
├── config/
│   └── database.php          # Configuração do banco de dados
├── public/
│   └── index.php             # Entry point da API
├── app/
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── DeviceController.php
│   │   └── MqttController.php
│   ├── models/
│   │   ├── User.php
│   │   ├── Device.php
│   │   └── MqttLog.php
│   ├── services/
│   │   └── MqttService.php
│   └── middleware/
│       └── AuthMiddleware.php
├── mqtt/
│   └── listener.php          # Script CLI para escuta MQTT
├── sql/
│   └── schema.sql            # Script de criação do banco
├── logs/
│   └── .gitkeep
├── .env.example
├── .htaccess
└── README.md
```

## Requisitos

- PHP 8.x
- MySQL 5.7+ ou MariaDB 10.3+
- Apache 2.4 com mod_rewrite ou Nginx
- Composer (gerenciador de dependências PHP)
- Extensão PHP: pdo_mysql, json, mbstring

## Instalação

### 1. Clone ou copie os arquivos para seu servidor

```bash
cp -r backend-php/ /var/www/html/iot-mqtt/
cd /var/www/html/iot-mqtt/
```

### 2. Instale as dependências

```bash
composer install
```

### 3. Configure o ambiente

```bash
cp .env.example .env
nano .env
```

### 4. Crie o banco de dados

```bash
mysql -u root -p < sql/schema.sql
```

### 5. Configure o Apache

```apache
<VirtualHost *:80>
    ServerName iot.seudominio.com
    DocumentRoot /var/www/html/iot-mqtt/public
    
    <Directory /var/www/html/iot-mqtt/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/iot-error.log
    CustomLog ${APACHE_LOG_DIR}/iot-access.log combined
</VirtualHost>
```

Ou para Nginx:

```nginx
server {
    listen 80;
    server_name iot.seudominio.com;
    root /var/www/html/iot-mqtt/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Inicie o listener MQTT

```bash
# Via systemd (recomendado)
sudo cp mqtt/iot-mqtt.service /etc/systemd/system/
sudo systemctl enable iot-mqtt
sudo systemctl start iot-mqtt

# Ou via screen/tmux
screen -S mqtt-listener
php mqtt/listener.php
# Ctrl+A, D para desanexar
```

## Usuário Administrador Padrão

- **Email:** admin@sistema.local
- **Senha:** admin123
- **Perfil:** administrador

⚠️ **IMPORTANTE:** Altere a senha após o primeiro login!

## Endpoints da API

### Autenticação

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/auth/login` | Login |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/auth/me` | Dados do usuário logado |

### Dispositivos

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/devices` | Listar dispositivos |
| GET | `/api/devices/{id}` | Obter dispositivo |
| POST | `/api/devices` | Criar dispositivo |
| PUT | `/api/devices/{id}` | Atualizar dispositivo |
| DELETE | `/api/devices/{id}` | Remover dispositivo |
| POST | `/api/devices/{id}/power` | Ligar/Desligar |

### MQTT

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/mqtt/config` | Obter configuração |
| PUT | `/api/mqtt/config` | Atualizar configuração |
| GET | `/api/mqtt/logs` | Histórico de logs |
| POST | `/api/mqtt/test` | Testar conexão |

### Dashboard

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/dashboard/stats` | Estatísticas |

## Configuração do Frontend React

No seu frontend React, configure a URL base da API:

```typescript
// src/config/api.ts
export const API_BASE_URL = 'https://iot.seudominio.com/api';
```

## Logs

Os logs são salvos em:
- `/var/www/html/iot-mqtt/logs/app.log` - Logs da aplicação
- `/var/www/html/iot-mqtt/logs/mqtt.log` - Logs do listener MQTT

## Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Sessões com tokens JWT
- Proteção CSRF
- Headers de segurança configurados
- Validação de entrada em todas as rotas
