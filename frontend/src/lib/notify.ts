// Tiny notification bus: hooks call notify.success/error,
// the <Notifier /> mounted in App renders them as snackbars.

export type NotificationSeverity = "success" | "error" | "info" | "warning"

export interface NotificationOptions {
  description?: string
}

export interface Notification {
  id: number
  severity: NotificationSeverity
  title: string
  description?: string
}

type Listener = (notification: Notification) => void

const listeners = new Set<Listener>()
let nextId = 1

function emit(severity: NotificationSeverity, title: string, options?: NotificationOptions) {
  const notification: Notification = { id: nextId++, severity, title, description: options?.description }
  listeners.forEach((listener) => listener(notification))
}

export const notify = {
  success: (title: string, options?: NotificationOptions) => emit("success", title, options),
  error: (title: string, options?: NotificationOptions) => emit("error", title, options),
  info: (title: string, options?: NotificationOptions) => emit("info", title, options),
  warning: (title: string, options?: NotificationOptions) => emit("warning", title, options),
  subscribe(listener: Listener): () => void {
    listeners.add(listener)
    return () => {
      listeners.delete(listener)
    }
  },
}
