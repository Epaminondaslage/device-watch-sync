export type DeviceStatus = 'online' | 'offline' | 'unknown';

export interface Device {
  id: string;
  name: string;
  macAddress: string;
  mqttId: string;
  ip: string;
  status: DeviceStatus;
  lastSeen: Date | null;
  createdAt: Date;
  lastPayload?: string;
}

export interface MqttConfig {
  host: string;
  port: number;
  username: string;
  password: string;
  connected: boolean;
}

export interface User {
  id: string;
  name: string;
  email: string;
  role: 'admin' | 'operator';
}

export interface DashboardStats {
  totalDevices: number;
  onlineDevices: number;
  offlineDevices: number;
  unknownDevices: number;
  lastUpdate: Date;
}

export interface CommandLog {
  id: string;
  deviceId: string;
  deviceName: string;
  command: 'ON' | 'OFF';
  timestamp: Date;
  success: boolean;
}
