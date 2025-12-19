import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Device, DeviceStatus } from '@/types/device';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';

const deviceSchema = z.object({
  name: z.string().min(1, 'Nome é obrigatório').max(100),
  macAddress: z.string().regex(/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/, 'MAC Address inválido'),
  mqttId: z.string().min(1, 'Identificador MQTT é obrigatório').max(50),
  ip: z.string().regex(/^(\d{1,3}\.){3}\d{1,3}$/, 'IP inválido'),
  status: z.enum(['online', 'offline', 'unknown']),
});

type DeviceFormData = z.infer<typeof deviceSchema>;

interface DeviceFormProps {
  device?: Device | null;
  open: boolean;
  onClose: () => void;
  onSubmit: (data: DeviceFormData & { id?: string }) => void;
}

export function DeviceForm({ device, open, onClose, onSubmit }: DeviceFormProps) {
  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors },
  } = useForm<DeviceFormData>({
    resolver: zodResolver(deviceSchema),
    defaultValues: device ? {
      name: device.name,
      macAddress: device.macAddress,
      mqttId: device.mqttId,
      ip: device.ip,
      status: device.status,
    } : {
      name: '',
      macAddress: '',
      mqttId: '',
      ip: '',
      status: 'unknown',
    },
  });

  const handleFormSubmit = (data: DeviceFormData) => {
    onSubmit({ ...data, id: device?.id });
    reset();
    onClose();
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="bg-card border-border">
        <DialogHeader>
          <DialogTitle className="text-foreground">
            {device ? 'Editar Dispositivo' : 'Novo Dispositivo'}
          </DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-4">
          <div>
            <Label htmlFor="name">Nome do Dispositivo</Label>
            <Input
              id="name"
              {...register('name')}
              placeholder="Ex: Sala - Luz Principal"
              className="bg-input border-border"
            />
            {errors.name && (
              <p className="text-destructive text-xs mt-1">{errors.name.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="macAddress">MAC Address</Label>
            <Input
              id="macAddress"
              {...register('macAddress')}
              placeholder="AA:BB:CC:DD:EE:FF"
              className="bg-input border-border font-mono"
            />
            {errors.macAddress && (
              <p className="text-destructive text-xs mt-1">{errors.macAddress.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="mqttId">Identificador MQTT</Label>
            <Input
              id="mqttId"
              {...register('mqttId')}
              placeholder="tasmota_device_01"
              className="bg-input border-border font-mono"
            />
            {errors.mqttId && (
              <p className="text-destructive text-xs mt-1">{errors.mqttId.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="ip">Endereço IP</Label>
            <Input
              id="ip"
              {...register('ip')}
              placeholder="192.168.1.100"
              className="bg-input border-border font-mono"
            />
            {errors.ip && (
              <p className="text-destructive text-xs mt-1">{errors.ip.message}</p>
            )}
          </div>

          <div>
            <Label>Status Inicial</Label>
            <Select
              value={watch('status')}
              onValueChange={(value: DeviceStatus) => setValue('status', value)}
            >
              <SelectTrigger className="bg-input border-border">
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-popover border-border">
                <SelectItem value="online">Online</SelectItem>
                <SelectItem value="offline">Offline</SelectItem>
                <SelectItem value="unknown">Indeterminado</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" variant="glow">
              {device ? 'Salvar' : 'Adicionar'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
