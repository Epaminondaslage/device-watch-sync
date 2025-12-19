import { MainLayout } from '@/components/layout/MainLayout';
import { useDeviceStore } from '@/stores/deviceStore';
import { formatDistanceToNow, format } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { CheckCircle, XCircle, Power, PowerOff } from 'lucide-react';

export default function History() {
  const { commandLogs } = useDeviceStore();

  return (
    <MainLayout>
      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold text-foreground">Histórico de Comandos</h1>
          <p className="text-muted-foreground mt-1">
            Registro de todos os comandos enviados aos dispositivos
          </p>
        </div>

        {/* Command List */}
        {commandLogs.length > 0 ? (
          <div className="glass-card overflow-hidden">
            <table className="w-full">
              <thead>
                <tr className="border-b border-border bg-secondary/30">
                  <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">
                    Status
                  </th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">
                    Dispositivo
                  </th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">
                    Comando
                  </th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">
                    Data/Hora
                  </th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">
                    Tempo
                  </th>
                </tr>
              </thead>
              <tbody>
                {commandLogs.map((log) => (
                  <tr
                    key={log.id}
                    className="border-b border-border/50 hover:bg-secondary/20 transition-colors"
                  >
                    <td className="py-3 px-4">
                      {log.success ? (
                        <CheckCircle className="w-5 h-5 text-success" />
                      ) : (
                        <XCircle className="w-5 h-5 text-destructive" />
                      )}
                    </td>
                    <td className="py-3 px-4 text-foreground font-medium">
                      {log.deviceName}
                    </td>
                    <td className="py-3 px-4">
                      <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${
                        log.command === 'ON'
                          ? 'bg-success/20 text-success'
                          : 'bg-destructive/20 text-destructive'
                      }`}>
                        {log.command === 'ON' ? (
                          <Power className="w-3 h-3" />
                        ) : (
                          <PowerOff className="w-3 h-3" />
                        )}
                        {log.command}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-muted-foreground font-mono text-sm">
                      {format(log.timestamp, "dd/MM/yyyy HH:mm:ss", { locale: ptBR })}
                    </td>
                    <td className="py-3 px-4 text-muted-foreground text-sm">
                      {formatDistanceToNow(log.timestamp, { addSuffix: true, locale: ptBR })}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="glass-card p-12 text-center">
            <p className="text-muted-foreground">
              Nenhum comando foi enviado ainda. Use os controles na página de dispositivos para enviar comandos.
            </p>
          </div>
        )}
      </div>
    </MainLayout>
  );
}
