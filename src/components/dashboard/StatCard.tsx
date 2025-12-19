import { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface StatCardProps {
  title: string;
  value: number | string;
  icon: LucideIcon;
  variant?: 'default' | 'success' | 'destructive' | 'warning' | 'muted';
  subtitle?: string;
}

const variantStyles = {
  default: 'border-primary/30 [&_.icon-bg]:bg-primary/20 [&_.icon]:text-primary',
  success: 'border-success/30 [&_.icon-bg]:bg-success/20 [&_.icon]:text-success',
  destructive: 'border-destructive/30 [&_.icon-bg]:bg-destructive/20 [&_.icon]:text-destructive',
  warning: 'border-warning/30 [&_.icon-bg]:bg-warning/20 [&_.icon]:text-warning',
  muted: 'border-muted [&_.icon-bg]:bg-muted [&_.icon]:text-muted-foreground',
};

export function StatCard({ title, value, icon: Icon, variant = 'default', subtitle }: StatCardProps) {
  return (
    <div className={cn(
      "glass-card p-6 border-l-4 slide-in",
      variantStyles[variant]
    )}>
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm text-muted-foreground font-medium">{title}</p>
          <p className="text-3xl font-bold text-foreground mt-1 font-mono">{value}</p>
          {subtitle && (
            <p className="text-xs text-muted-foreground mt-2">{subtitle}</p>
          )}
        </div>
        <div className="icon-bg p-3 rounded-lg">
          <Icon className="icon w-6 h-6" />
        </div>
      </div>
    </div>
  );
}
