import { useEffect, useState } from 'react';
import { MainLayout } from '@/components/layout/MainLayout';
import { StatCard } from '@/components/dashboard/StatCard';
import { useDeviceStore } from '@/stores/deviceStore';
import { Cpu, Wifi, WifiOff, HelpCircle, Clock, Radio } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { DeviceStatusBadge } from '@/components/devices/DeviceStatusBadge';

export default function Dashboard() {
  const { devices, getStats, mqttConfig, commandLogs } = useDeviceStore();
  const [stats, setStats] = useState(getStats());
  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const interval = setInterval(() => {
      setStats(getStats());
      setCurrentTime(new Date());
    }, 5000);

    return () => clearInterval(interval);
  }, [getStats]);

  const recentDevices = [...devices]
    .filter((d) => d.lastSeen)
    .sort((a, b) => (b.lastSeen?.getTime() || 0) - (a.lastSeen?.getTime() || 0))
    .slice(0, 5);

  const recentCommands = commandLogs.slice(0, 5);

  return (
    <MainLayout>
      <div className="space-y-8">
        {/* Header */}
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Dashboard</h1>
            <p className="text-muted-foreground mt-1">
              Visão geral do sistema de monitoramento IoT
            </p>
          </div>
          <div className="flex items-center gap-3 text-sm text-muted-foreground">
            <Clock className="w-4 h-4" />
            <span>Última atualização: {currentTime.toLocaleTimeString('pt-BR')}</span>
          </div>
        </div>

        {/* Stats Grid */}
        <div className="data-grid">
          <StatCard
            title="Total de Dispositivos"
            value={stats.totalDevices}
            icon={Cpu}
            variant="default"
            subtitle="Cadastrados no sistema"
          />
          <StatCard
            title="Dispositivos Online"
            value={stats.onlineDevices}
            icon={Wifi}
            variant="success"
            subtitle="Comunicação ativa"
          />
          <StatCard
            title="Dispositivos Offline"
            value={stats.offlineDevices}
            icon={WifiOff}
            variant="destructive"
            subtitle="Sem comunicação"
          />
          <StatCard
            title="Status Indeterminado"
            value={stats.unknownDevices}
            icon={HelpCircle}
            variant="muted"
            subtitle="Aguardando resposta"
          />
        </div>

        {/* MQTT Status Banner */}
        <div className="glass-card p-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className={`p-2 rounded-lg ${mqttConfig.connected ? 'bg-success/20' : 'bg-destructive/20'}`}>
              <Radio className={`w-5 h-5 ${mqttConfig.connected ? 'text-success' : 'text-destructive'}`} />
            </div>
            <div>
              <p className="font-medium text-foreground">Broker MQTT</p>
              <p className="text-sm text-muted-foreground font-mono">
                {mqttConfig.host}:{mqttConfig.port}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <div className={`w-2 h-2 rounded-full pulse-dot ${mqttConfig.connected ? 'bg-success' : 'bg-destructive'}`} />
            <span className={`text-sm font-medium ${mqttConfig.connected ? 'text-success' : 'text-destructive'}`}>
              {mqttConfig.connected ? 'Conectado' : 'Desconectado'}
            </span>
          </div>
        </div>

        {/* Two Column Layout */}
        <div className="grid lg:grid-cols-2 gap-6">
          {/* Recent Activity */}
          <div className="glass-card p-6">
            <h2 className="text-lg font-semibold text-foreground mb-4">
              Atividade Recente
            </h2>
            <div className="space-y-3">
              {recentDevices.length > 0 ? (
                recentDevices.map((device) => (
                  <div
                    key={device.id}
                    className="flex items-center justify-between p-3 bg-secondary/30 rounded-lg"
                  >
                    <div className="flex items-center gap-3">
                      <Wifi className="w-4 h-4 text-primary" />
                      <div>
                        <p className="text-sm font-medium text-foreground">{device.name}</p>
                        <p className="text-xs text-muted-foreground">
                          {device.lastSeen
                            ? formatDistanceToNow(device.lastSeen, { addSuffix: true, locale: ptBR })
                            : 'Nunca'}
                        </p>
                      </div>
                    </div>
                    <DeviceStatusBadge status={device.status} />
                  </div>
                ))
              ) : (
                <p className="text-muted-foreground text-sm text-center py-4">
                  Nenhuma atividade recente
                </p>
              )}
            </div>
          </div>

          {/* Command History */}
          <div className="glass-card p-6">
            <h2 className="text-lg font-semibold text-foreground mb-4">
              Últimos Comandos
            </h2>
            <div className="space-y-3">
              {recentCommands.length > 0 ? (
                recentCommands.map((log) => (
                  <div
                    key={log.id}
                    className="flex items-center justify-between p-3 bg-secondary/30 rounded-lg"
                  >
                    <div className="flex items-center gap-3">
                      <div className={`w-2 h-2 rounded-full ${log.success ? 'bg-success' : 'bg-destructive'}`} />
                      <div>
                        <p className="text-sm font-medium text-foreground">{log.deviceName}</p>
                        <p className="text-xs text-muted-foreground">
                          {formatDistanceToNow(log.timestamp, { addSuffix: true, locale: ptBR })}
                        </p>
                      </div>
                    </div>
                    <span className={`text-xs font-mono px-2 py-1 rounded ${
                      log.command === 'ON' 
                        ? 'bg-success/20 text-success' 
                        : 'bg-destructive/20 text-destructive'
                    }`}>
                      {log.command}
                    </span>
                  </div>
                ))
              ) : (
                <p className="text-muted-foreground text-sm text-center py-4">
                  Nenhum comando enviado
                </p>
              )}
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
}
