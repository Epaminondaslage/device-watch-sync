import { DeviceStatus } from '@/types/device';
import { cn } from '@/lib/utils';

interface DeviceStatusBadgeProps {
  status: DeviceStatus;
}

const statusConfig = {
  online: {
    label: 'Online',
    className: 'status-online',
  },
  offline: {
    label: 'Offline',
    className: 'status-offline',
  },
  unknown: {
    label: 'Indeterminado',
    className: 'status-unknown',
  },
};

export function DeviceStatusBadge({ status }: DeviceStatusBadgeProps) {
  const config = statusConfig[status];
  
  return (
    <span className={cn(
      "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium",
      config.className
    )}>
      <span className={cn(
        "w-1.5 h-1.5 rounded-full",
        status === 'online' && "bg-success-foreground pulse-dot",
        status === 'offline' && "bg-destructive-foreground",
        status === 'unknown' && "bg-muted-foreground"
      )} />
      {config.label}
    </span>
  );
}
