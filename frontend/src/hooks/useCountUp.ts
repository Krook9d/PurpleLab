import { useEffect, useRef, useState } from "react"

const prefersReducedMotion = () =>
  typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches

// Animates a number from its previous value to `target` (ease-out cubic).
export function useCountUp(target: number | undefined, durationMs = 900): number | undefined {
  const [value, setValue] = useState<number | undefined>(undefined)
  const fromRef = useRef(0)
  const reduced = prefersReducedMotion()

  useEffect(() => {
    if (target === undefined || reduced) return
    const from = fromRef.current
    const start = performance.now()
    let frame = 0
    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / durationMs)
      setValue(from + (target - from) * (1 - Math.pow(1 - t, 3)))
      if (t < 1) frame = requestAnimationFrame(tick)
      else fromRef.current = target
    }
    frame = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(frame)
  }, [target, durationMs, reduced])

  return reduced ? target : value
}
