import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { 
  LayoutDashboard, 
  Cpu, 
  Settings, 
  LogOut, 
  Radio,
  History,
  ChevronLeft,
  ChevronRight,
  Wifi
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useDeviceStore } from '@/stores/deviceStore';
import { Button } from '@/components/ui/button';

const navigation = [
  { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  { name: 'Dispositivos', href: '/devices', icon: Cpu },
  { name: 'Histórico', href: '/history', icon: History },
  { name: 'MQTT Config', href: '/mqtt', icon: Radio },
];

export function Sidebar() {
  const [collapsed, setCollapsed] = useState(false);
  const location = useLocation();
  const { logout, user, mqttConfig } = useDeviceStore();

  return (
    <aside
      className={cn(
        "fixed left-0 top-0 z-40 h-screen bg-sidebar border-r border-sidebar-border transition-all duration-300",
        collapsed ? "w-16" : "w-64"
      )}
    >
      <div className="flex h-full flex-col">
        {/* Logo */}
        <div className="flex h-16 items-center justify-between px-4 border-b border-sidebar-border">
          {!collapsed && (
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center glow-primary">
                <Wifi className="w-5 h-5 text-primary-foreground" />
              </div>
              <span className="font-semibold text-foreground">IoT Monitor</span>
            </div>
          )}
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setCollapsed(!collapsed)}
            className="h-8 w-8 text-sidebar-foreground hover:text-foreground"
          >
            {collapsed ? <ChevronRight className="w-4 h-4" /> : <ChevronLeft className="w-4 h-4" />}
          </Button>
        </div>

        {/* MQTT Status */}
        <div className={cn(
          "px-4 py-3 border-b border-sidebar-border",
          collapsed && "px-2"
        )}>
          <div className={cn(
            "flex items-center gap-2",
            collapsed && "justify-center"
          )}>
            <div className={cn(
              "w-2 h-2 rounded-full pulse-dot",
              mqttConfig.connected ? "bg-success glow-success" : "bg-destructive glow-destructive"
            )} />
            {!collapsed && (
              <span className="text-xs text-muted-foreground">
                Broker: {mqttConfig.connected ? 'Conectado' : 'Desconectado'}
              </span>
            )}
          </div>
        </div>

        {/* Navigation */}
        <nav className="flex-1 px-2 py-4 space-y-1">
          {navigation.map((item) => {
            const isActive = location.pathname === item.href;
            return (
              <Link
                key={item.name}
                to={item.href}
                className={cn(
                  "flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200",
                  isActive
                    ? "bg-sidebar-accent text-primary border border-primary/30"
                    : "text-sidebar-foreground hover:bg-sidebar-accent hover:text-foreground",
                  collapsed && "justify-center px-2"
                )}
              >
                <item.icon className={cn("w-5 h-5 flex-shrink-0", isActive && "text-primary")} />
                {!collapsed && <span>{item.name}</span>}
              </Link>
            );
          })}
        </nav>

        {/* User Section */}
        <div className="border-t border-sidebar-border p-4">
          {!collapsed && user && (
            <div className="mb-3 px-2">
              <p className="text-sm font-medium text-foreground">{user.name}</p>
              <p className="text-xs text-muted-foreground capitalize">{user.role}</p>
            </div>
          )}
          <Button
            variant="ghost"
            onClick={logout}
            className={cn(
              "w-full text-sidebar-foreground hover:text-destructive hover:bg-destructive/10",
              collapsed ? "px-2" : "justify-start"
            )}
          >
            <LogOut className="w-5 h-5" />
            {!collapsed && <span className="ml-2">Sair</span>}
          </Button>
        </div>
      </div>
    </aside>
  );
}
