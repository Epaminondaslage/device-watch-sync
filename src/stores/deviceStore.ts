import { create } from 'zustand';
import { Device, MqttConfig, User, CommandLog, DashboardStats } from '@/types/device';

interface DeviceStore {
  devices: Device[];
  mqttConfig: MqttConfig;
  user: User | null;
  commandLogs: CommandLog[];
  isAuthenticated: boolean;
  
  // Actions
  setDevices: (devices: Device[]) => void;
  addDevice: (device: Device) => void;
  updateDevice: (id: string, updates: Partial<Device>) => void;
  removeDevice: (id: string) => void;
  setMqttConfig: (config: Partial<MqttConfig>) => void;
  login: (user: User) => void;
  logout: () => void;
  addCommandLog: (log: CommandLog) => void;
  toggleDevicePower: (id: string, power: 'ON' | 'OFF') => void;
  getStats: () => DashboardStats;
}

// Mock initial devices
const mockDevices: Device[] = [
  {
    id: '1',
    name: 'Sala Principal - Luz',
    macAddress: 'AA:BB:CC:DD:EE:01',
    mqttId: 'tasmota_sala_luz',
    ip: '192.168.1.101',
    status: 'online',
    lastSeen: new Date(Date.now() - 30000),
    createdAt: new Date('2024-01-15'),
    lastPayload: '{"POWER":"ON"}',
  },
  {
    id: '2',
    name: 'Cozinha - Tomada',
    macAddress: 'AA:BB:CC:DD:EE:02',
    mqttId: 'tasmota_cozinha_tomada',
    ip: '192.168.1.102',
    status: 'online',
    lastSeen: new Date(Date.now() - 60000),
    createdAt: new Date('2024-01-16'),
    lastPayload: '{"POWER":"OFF"}',
  },
  {
    id: '3',
    name: 'Quarto - Ventilador',
    macAddress: 'AA:BB:CC:DD:EE:03',
    mqttId: 'tasmota_quarto_vent',
    ip: '192.168.1.103',
    status: 'offline',
    lastSeen: new Date(Date.now() - 3600000),
    createdAt: new Date('2024-02-01'),
  },
  {
    id: '4',
    name: 'Garagem - Portão',
    macAddress: 'AA:BB:CC:DD:EE:04',
    mqttId: 'tasmota_garagem_portao',
    ip: '192.168.1.104',
    status: 'unknown',
    lastSeen: null,
    createdAt: new Date('2024-02-10'),
  },
  {
    id: '5',
    name: 'Escritório - Monitor',
    macAddress: 'AA:BB:CC:DD:EE:05',
    mqttId: 'tasmota_escritorio_monitor',
    ip: '192.168.1.105',
    status: 'online',
    lastSeen: new Date(Date.now() - 15000),
    createdAt: new Date('2024-02-15'),
    lastPayload: '{"POWER":"ON"}',
  },
];

export const useDeviceStore = create<DeviceStore>((set, get) => ({
  devices: mockDevices,
  mqttConfig: {
    host: 'broker.local',
    port: 1883,
    username: '',
    password: '',
    connected: true,
  },
  user: null,
  commandLogs: [],
  isAuthenticated: false,

  setDevices: (devices) => set({ devices }),
  
  addDevice: (device) => set((state) => ({ 
    devices: [...state.devices, device] 
  })),
  
  updateDevice: (id, updates) => set((state) => ({
    devices: state.devices.map((d) => 
      d.id === id ? { ...d, ...updates } : d
    ),
  })),
  
  removeDevice: (id) => set((state) => ({
    devices: state.devices.filter((d) => d.id !== id),
  })),
  
  setMqttConfig: (config) => set((state) => ({
    mqttConfig: { ...state.mqttConfig, ...config },
  })),
  
  login: (user) => set({ user, isAuthenticated: true }),
  
  logout: () => set({ user: null, isAuthenticated: false }),
  
  addCommandLog: (log) => set((state) => ({
    commandLogs: [log, ...state.commandLogs].slice(0, 100),
  })),
  
  toggleDevicePower: (id, power) => {
    const device = get().devices.find((d) => d.id === id);
    if (device) {
      const log: CommandLog = {
        id: Date.now().toString(),
        deviceId: id,
        deviceName: device.name,
        command: power,
        timestamp: new Date(),
        success: Math.random() > 0.1, // 90% success rate for demo
      };
      
      set((state) => ({
        devices: state.devices.map((d) =>
          d.id === id
            ? { ...d, lastPayload: `{"POWER":"${power}"}`, lastSeen: new Date() }
            : d
        ),
        commandLogs: [log, ...state.commandLogs].slice(0, 100),
      }));
    }
  },
  
  getStats: () => {
    const devices = get().devices;
    return {
      totalDevices: devices.length,
      onlineDevices: devices.filter((d) => d.status === 'online').length,
      offlineDevices: devices.filter((d) => d.status === 'offline').length,
      unknownDevices: devices.filter((d) => d.status === 'unknown').length,
      lastUpdate: new Date(),
    };
  },
}));
