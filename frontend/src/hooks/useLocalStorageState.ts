import { useCallback, useState } from "react"

// useState persisted in localStorage (per-browser UI preferences only).
export function useLocalStorageState<T extends string>(key: string, initial: T, allowed: readonly T[]) {
  const [value, setValue] = useState<T>(() => {
    try {
      const stored = localStorage.getItem(key)
      return stored && (allowed as readonly string[]).includes(stored) ? (stored as T) : initial
    } catch {
      return initial
    }
  })
  const update = useCallback(
    (next: T) => {
      setValue(next)
      try {
        localStorage.setItem(key, next)
      } catch {
        // Storage unavailable: the preference just isn't persisted.
      }
    },
    [key],
  )
  return [value, update] as const
}
