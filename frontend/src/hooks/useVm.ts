import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { notify } from "@/lib/notify"
import { api } from "@/lib/apiClient"
import type { VmState } from "@/lib/types"

export function useVmState() {
  return useQuery<VmState>({
    queryKey: ["vm", "state"],
    queryFn: () => api.get<VmState>("/vm/state"),
    refetchInterval: 5000,
  })
}

function useVmAction(path: string, successMessage: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api.post<{ message: string }>(path),
    onSuccess: () => {
      notify.success(successMessage)
      queryClient.invalidateQueries({ queryKey: ["vm", "state"] })
    },
    onError: (err) => {
      notify.error("VM action failed", { description: (err as Error).message })
    },
  })
}

export function useVmPoweroff() {
  return useVmAction("/vm/poweroff", "VM powered off")
}

export function useVmRestore() {
  return useVmAction("/vm/restore", "VM restored from snapshot")
}

export function useVmStart() {
  return useVmAction("/vm/start", "VM started")
}

export function useVmEnableAv() {
  return useVmAction("/vm/enable-av", "Antivirus enabled")
}

export function useVmDisableAv() {
  return useVmAction("/vm/disable-av", "Antivirus disabled")
}

export function useForensicAcquisition() {
  return useMutation({
    mutationFn: (acquisitionType: "memory" | "disk") =>
      api.post<{ message: string; output_path: string | null }>("/vm/forensic", {
        acquisition_type: acquisitionType,
      }),
    onSuccess: (data) => {
      notify.success("Forensic acquisition completed", { description: data.output_path ?? undefined })
    },
    onError: (err) => {
      notify.error("Forensic acquisition failed", { description: (err as Error).message })
    },
  })
}
