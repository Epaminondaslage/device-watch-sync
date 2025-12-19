import { Device } from '@/types/device';
import { DeviceStatusBadge } from './DeviceStatusBadge';
import { Button } from '@/components/ui/button';
import { Power, PowerOff, Edit, Trash2, Clock, Wifi } from 'lucide-react';
import { useDeviceStore } from '@/stores/deviceStore';
import { formatDistanceToNow } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { toast } from 'sonner';

interface DeviceCardProps {
  device: Device;
  onEdit: (device: Device) => void;
  onDelete: (device: Device) => void;
}

export function DeviceCard({ device, onEdit, onDelete }: DeviceCardProps) {
  const { toggleDevicePower } = useDeviceStore();

  const handlePower = (power: 'ON' | 'OFF') => {
    toggleDevicePower(device.id, power);
    toast.success(`Comando ${power} enviado para ${device.name}`);
  };

  const getPowerState = () => {
    if (!device.lastPayload) return null;
    try {
      const payload = JSON.parse(device.lastPayload);
      return payload.POWER;
    } catch {
      return null;
    }
  };

  const powerState = getPowerState();

  return (
    <div className="glass-card p-5 hover:border-primary/40 transition-all duration-300 fade-in">
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-secondary flex items-center justify-center">
            <Wifi className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h3 className="font-semibold text-foreground">{device.name}</h3>
            <p className="text-xs text-muted-foreground font-mono">{device.mqttId}</p>
          </div>
        </div>
        <DeviceStatusBadge status={device.status} />
      </div>

      <div className="space-y-2 mb-4 text-sm">
        <div className="flex justify-between">
          <span className="text-muted-foreground">IP:</span>
          <span className="font-mono text-foreground">{device.ip}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-muted-foreground">MAC:</span>
          <span className="font-mono text-foreground text-xs">{device.macAddress}</span>
        </div>
        <div className="flex items-center justify-between">
          <span className="text-muted-foreground flex items-center gap-1">
            <Clock className="w-3 h-3" />
            Última comunicação:
          </span>
          <span className="text-foreground text-xs">
            {device.lastSeen 
              ? formatDistanceToNow(device.lastSeen, { addSuffix: true, locale: ptBR })
              : 'Nunca'
            }
          </span>
        </div>
        {powerState && (
          <div className="flex justify-between">
            <span className="text-muted-foreground">Estado:</span>
            <span className={powerState === 'ON' ? 'text-success font-medium' : 'text-muted-foreground'}>
              {powerState}
            </span>
          </div>
        )}
      </div>

      <div className="flex gap-2 pt-3 border-t border-border">
        <Button
          variant="success"
          size="sm"
          onClick={() => handlePower('ON')}
          disabled={device.status === 'offline'}
          className="flex-1"
        >
          <Power className="w-4 h-4" />
          Ligar
        </Button>
        <Button
          variant="destructive"
          size="sm"
          onClick={() => handlePower('OFF')}
          disabled={device.status === 'offline'}
          className="flex-1"
        >
          <PowerOff className="w-4 h-4" />
          Desligar
        </Button>
        <Button variant="control" size="icon" onClick={() => onEdit(device)}>
          <Edit className="w-4 h-4" />
        </Button>
        <Button variant="control" size="icon" onClick={() => onDelete(device)}>
          <Trash2 className="w-4 h-4 text-destructive" />
        </Button>
      </div>
    </div>
  );
}
