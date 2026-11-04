/*
 * Adapted from OpenCTI (opencti-front/src/components/Transition.tsx), modified for PurpleLab.
 * Original work Copyright (c) 2021-2026 Filigran SAS, licensed under Apache-2.0.
 * See LICENSES/Apache-2.0.txt.
 *
 * Modifications: none besides typing/formatting.
 */
import { forwardRef, type ReactElement, type Ref } from "react"
import { Slide } from "@mui/material"
import type { TransitionProps } from "@mui/material/transitions"

export const Transition = forwardRef(function Transition(
  { children, ...props }: TransitionProps & { children: ReactElement },
  ref: Ref<unknown>,
) {
  return (
    <Slide direction="up" ref={ref} {...props}>
      {children}
    </Slide>
  )
})
