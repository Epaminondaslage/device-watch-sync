import { useState } from 'react';
import { MainLayout } from '@/components/layout/MainLayout';
import { useDeviceStore } from '@/stores/deviceStore';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Radio, Save, RefreshCw, Eye, EyeOff, CheckCircle, XCircle } from 'lucide-react';
import { toast } from 'sonner';

export default function MqttConfig() {
  const { mqttConfig, setMqttConfig } = useDeviceStore();
  const [formData, setFormData] = useState(mqttConfig);
  const [showPassword, setShowPassword] = useState(false);
  const [isTesting, setIsTesting] = useState(false);

  const handleSave = () => {
    setMqttConfig(formData);
    toast.success('Configurações MQTT salvas com sucesso');
  };

  const handleTestConnection = async () => {
    setIsTesting(true);
    // Simulate connection test
    await new Promise((resolve) => setTimeout(resolve, 1500));
    
    const success = Math.random() > 0.2; // 80% success rate for demo
    
    if (success) {
      setMqttConfig({ connected: true });
      toast.success('Conexão com broker estabelecida com sucesso');
    } else {
      setMqttConfig({ connected: false });
      toast.error('Falha ao conectar com o broker MQTT');
    }
    
    setIsTesting(false);
  };

  return (
    <MainLayout>
      <div className="max-w-2xl space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold text-foreground">Configuração MQTT</h1>
          <p className="text-muted-foreground mt-1">
            Configure a conexão com o broker MQTT
          </p>
        </div>

        {/* Connection Status */}
        <div className={`glass-card p-4 flex items-center gap-4 border-l-4 ${
          mqttConfig.connected ? 'border-l-success' : 'border-l-destructive'
        }`}>
          <div className={`p-3 rounded-full ${
            mqttConfig.connected ? 'bg-success/20' : 'bg-destructive/20'
          }`}>
            {mqttConfig.connected ? (
              <CheckCircle className="w-6 h-6 text-success" />
            ) : (
              <XCircle className="w-6 h-6 text-destructive" />
            )}
          </div>
          <div>
            <p className="font-medium text-foreground">
              Status da Conexão
            </p>
            <p className="text-sm text-muted-foreground">
              {mqttConfig.connected 
                ? 'Conectado ao broker MQTT' 
                : 'Desconectado do broker MQTT'
              }
            </p>
          </div>
        </div>

        {/* Configuration Form */}
        <div className="glass-card p-6 space-y-6">
          <div className="flex items-center gap-3 mb-6">
            <div className="p-2 rounded-lg bg-primary/20">
              <Radio className="w-5 h-5 text-primary" />
            </div>
            <h2 className="text-lg font-semibold text-foreground">
              Configurações do Broker
            </h2>
          </div>

          <div className="grid gap-6">
            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="host">Host do Broker</Label>
                <Input
                  id="host"
                  value={formData.host}
                  onChange={(e) => setFormData({ ...formData, host: e.target.value })}
                  placeholder="broker.mqtt.local"
                  className="bg-input border-border font-mono mt-1.5"
                />
              </div>
              <div>
                <Label htmlFor="port">Porta</Label>
                <Input
                  id="port"
                  type="number"
                  value={formData.port}
                  onChange={(e) => setFormData({ ...formData, port: parseInt(e.target.value) || 1883 })}
                  placeholder="1883"
                  className="bg-input border-border font-mono mt-1.5"
                />
              </div>
            </div>

            <div>
              <Label htmlFor="username">Usuário (opcional)</Label>
              <Input
                id="username"
                value={formData.username}
                onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                placeholder="mqtt_user"
                className="bg-input border-border mt-1.5"
              />
            </div>

            <div>
              <Label htmlFor="password">Senha (opcional)</Label>
              <div className="relative mt-1.5">
                <Input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  placeholder="••••••••"
                  className="bg-input border-border pr-10"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>
          </div>

          <div className="flex gap-3 pt-4 border-t border-border">
            <Button variant="glow" onClick={handleSave}>
              <Save className="w-4 h-4" />
              Salvar Configurações
            </Button>
            <Button 
              variant="outline" 
              onClick={handleTestConnection}
              disabled={isTesting}
            >
              <RefreshCw className={`w-4 h-4 ${isTesting ? 'animate-spin' : ''}`} />
              {isTesting ? 'Testando...' : 'Testar Conexão'}
            </Button>
          </div>
        </div>

        {/* Topic Configuration */}
        <div className="glass-card p-6">
          <h2 className="text-lg font-semibold text-foreground mb-4">
            Padrão de Tópicos
          </h2>
          <div className="space-y-3 text-sm">
            <div className="flex items-center gap-2 p-3 bg-secondary/30 rounded-lg">
              <code className="font-mono text-primary">tele/&lt;device&gt;/LWT</code>
              <span className="text-muted-foreground">- Status de conexão (Online/Offline)</span>
            </div>
            <div className="flex items-center gap-2 p-3 bg-secondary/30 rounded-lg">
              <code className="font-mono text-primary">tele/&lt;device&gt;/STATE</code>
              <span className="text-muted-foreground">- Estado atual do dispositivo</span>
            </div>
            <div className="flex items-center gap-2 p-3 bg-secondary/30 rounded-lg">
              <code className="font-mono text-primary">cmnd/&lt;device&gt;/POWER</code>
              <span className="text-muted-foreground">- Comandos de liga/desliga</span>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
