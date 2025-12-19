import { useState, useMemo } from 'react';
import { MainLayout } from '@/components/layout/MainLayout';
import { DeviceCard } from '@/components/devices/DeviceCard';
import { DeviceForm } from '@/components/devices/DeviceForm';
import { useDeviceStore } from '@/stores/deviceStore';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Plus, Search, Filter } from 'lucide-react';
import { Device, DeviceStatus } from '@/types/device';
import { toast } from 'sonner';

export default function Devices() {
  const { devices, addDevice, updateDevice, removeDevice } = useDeviceStore();
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<DeviceStatus | 'all'>('all');
  const [formOpen, setFormOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<Device | null>(null);
  const [deletingDevice, setDeletingDevice] = useState<Device | null>(null);

  const filteredDevices = useMemo(() => {
    return devices.filter((device) => {
      const matchesSearch =
        device.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        device.mqttId.toLowerCase().includes(searchTerm.toLowerCase()) ||
        device.ip.includes(searchTerm) ||
        device.macAddress.toLowerCase().includes(searchTerm.toLowerCase());

      const matchesStatus = statusFilter === 'all' || device.status === statusFilter;

      return matchesSearch && matchesStatus;
    });
  }, [devices, searchTerm, statusFilter]);

  const handleSubmit = (data: any) => {
    if (data.id) {
      updateDevice(data.id, data);
      toast.success('Dispositivo atualizado com sucesso');
    } else {
      const newDevice: Device = {
        id: Date.now().toString(),
        ...data,
        lastSeen: null,
        createdAt: new Date(),
      };
      addDevice(newDevice);
      toast.success('Dispositivo adicionado com sucesso');
    }
  };

  const handleEdit = (device: Device) => {
    setEditingDevice(device);
    setFormOpen(true);
  };

  const handleDelete = (device: Device) => {
    setDeletingDevice(device);
  };

  const confirmDelete = () => {
    if (deletingDevice) {
      removeDevice(deletingDevice.id);
      toast.success('Dispositivo removido com sucesso');
      setDeletingDevice(null);
    }
  };

  const handleCloseForm = () => {
    setFormOpen(false);
    setEditingDevice(null);
  };

  return (
    <MainLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Dispositivos</h1>
            <p className="text-muted-foreground mt-1">
              Gerencie seus dispositivos IoT conectados
            </p>
          </div>
          <Button variant="glow" onClick={() => setFormOpen(true)}>
            <Plus className="w-4 h-4" />
            Novo Dispositivo
          </Button>
        </div>

        {/* Filters */}
        <div className="glass-card p-4 flex flex-col md:flex-row gap-4">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <Input
              placeholder="Buscar por nome, MQTT ID, IP ou MAC..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-10 bg-input border-border"
            />
          </div>
          <div className="flex items-center gap-2">
            <Filter className="w-4 h-4 text-muted-foreground" />
            <Select
              value={statusFilter}
              onValueChange={(value) => setStatusFilter(value as DeviceStatus | 'all')}
            >
              <SelectTrigger className="w-48 bg-input border-border">
                <SelectValue placeholder="Filtrar por status" />
              </SelectTrigger>
              <SelectContent className="bg-popover border-border">
                <SelectItem value="all">Todos os status</SelectItem>
                <SelectItem value="online">Online</SelectItem>
                <SelectItem value="offline">Offline</SelectItem>
                <SelectItem value="unknown">Indeterminado</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Results Count */}
        <p className="text-sm text-muted-foreground">
          {filteredDevices.length} dispositivo{filteredDevices.length !== 1 ? 's' : ''} encontrado{filteredDevices.length !== 1 ? 's' : ''}
        </p>

        {/* Device Grid */}
        {filteredDevices.length > 0 ? (
          <div className="data-grid">
            {filteredDevices.map((device) => (
              <DeviceCard
                key={device.id}
                device={device}
                onEdit={handleEdit}
                onDelete={handleDelete}
              />
            ))}
          </div>
        ) : (
          <div className="glass-card p-12 text-center">
            <p className="text-muted-foreground">
              {devices.length === 0
                ? 'Nenhum dispositivo cadastrado. Clique em "Novo Dispositivo" para começar.'
                : 'Nenhum dispositivo encontrado com os filtros aplicados.'}
            </p>
          </div>
        )}
      </div>

      {/* Device Form Modal */}
      <DeviceForm
        device={editingDevice}
        open={formOpen}
        onClose={handleCloseForm}
        onSubmit={handleSubmit}
      />

      {/* Delete Confirmation */}
      <AlertDialog open={!!deletingDevice} onOpenChange={() => setDeletingDevice(null)}>
        <AlertDialogContent className="bg-card border-border">
          <AlertDialogHeader>
            <AlertDialogTitle>Confirmar exclusão</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja remover o dispositivo "{deletingDevice?.name}"?
              Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="bg-secondary border-border">
              Cancelar
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Excluir
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </MainLayout>
  );
}
