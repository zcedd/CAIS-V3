"use client"

import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

type TimelineColor =
  | "primary"
  | "secondary"
  | "info"
  | "success"
  | "warning"
  | "error"

type TimelineOrientation = "vertical" | "horizontal"

type TimelineContextValue = {
  color: TimelineColor
  orientation: TimelineOrientation
}

const TimelineContext = React.createContext<TimelineContextValue | null>(null)

function useTimelineContext(component: string): TimelineContextValue {
  const context = React.useContext(TimelineContext)

  if (!context) {
    throw new Error(`<${component}> must be used inside a <Timeline>.`)
  }

  return context
}

const timelineVariants = cva("group/timeline flex w-full text-sm", {
  variants: {
    orientation: {
      vertical: "flex-col",
      horizontal: "flex-row items-start overflow-x-auto",
    },
  },
  defaultVariants: {
    orientation: "vertical",
  },
})

type TimelineProps = React.ComponentProps<"ol"> &
  VariantProps<typeof timelineVariants> & {
    color?: TimelineColor
  }

function Timeline({
  className,
  color = "secondary",
  orientation = "vertical",
  children,
  ...props
}: TimelineProps) {
  const contextValue = React.useMemo<TimelineContextValue>(
    () => ({
      color,
      orientation: orientation ?? "vertical",
    }),
    [color, orientation],
  )

  return (
    <TimelineContext.Provider value={contextValue}>
      <ol
        data-slot="timeline"
        data-color={color}
        data-orientation={orientation}
        className={cn(timelineVariants({ orientation }), className)}
        {...props}
      >
        {children}
      </ol>
    </TimelineContext.Provider>
  )
}

function TimelineItem({
  className,
  ...props
}: React.ComponentProps<"li">) {
  const { orientation } = useTimelineContext("TimelineItem")

  return (
    <li
      data-slot="timeline-item"
      data-orientation={orientation}
      className={cn(
        "group/timeline-item relative flex gap-4",
        orientation === "horizontal" && "min-w-44 flex-1 flex-col",
        className,
      )}
      {...props}
    />
  )
}

function TimelineHeader({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const { orientation } = useTimelineContext("TimelineHeader")

  return (
    <div
      data-slot="timeline-header"
      data-orientation={orientation}
      className={cn(
        "relative flex shrink-0 items-center",
        orientation === "vertical"
          ? "w-7 flex-col self-stretch"
          : "h-7 w-full flex-row",
        className,
      )}
      {...props}
    />
  )
}

const iconColorVariants = cva(
  "inline-flex size-3 shrink-0 items-center justify-center rounded-full ring-4 ring-background [&>svg]:size-3",
  {
    variants: {
      color: {
        primary: "bg-primary text-primary-foreground",
        secondary: "bg-muted-foreground text-background",
        info: "bg-sky-500 text-white",
        success: "bg-emerald-500 text-white",
        warning: "bg-amber-500 text-white",
        error: "bg-destructive text-destructive-foreground",
      },
      withIcon: {
        true: "size-7 ring-2 [&>svg]:size-4",
        false: "",
      },
    },
    defaultVariants: {
      color: "secondary",
      withIcon: false,
    },
  },
)

type TimelineIconProps = React.ComponentProps<"span"> & {
  color?: TimelineColor
}

function TimelineIcon({
  className,
  color,
  children,
  ...props
}: TimelineIconProps) {
  const context = useTimelineContext("TimelineIcon")
  const resolvedColor = color ?? context.color
  const hasIcon = React.Children.count(children) > 0

  return (
    <span
      data-slot="timeline-icon"
      data-color={resolvedColor}
      className={cn(
        iconColorVariants({ color: resolvedColor, withIcon: hasIcon }),
        className,
      )}
      {...props}
    >
      {children}
    </span>
  )
}

const separatorColorVariants = cva("shrink-0", {
  variants: {
    color: {
      primary: "bg-primary/30",
      secondary: "bg-border",
      info: "bg-sky-500/30",
      success: "bg-emerald-500/30",
      warning: "bg-amber-500/30",
      error: "bg-destructive/30",
    },
    orientation: {
      vertical: "mt-1 w-px flex-1 group-last/timeline-item:hidden",
      horizontal: "ml-1 h-px flex-1 group-last/timeline-item:hidden",
    },
  },
  defaultVariants: {
    color: "secondary",
    orientation: "vertical",
  },
})

type TimelineSeparatorProps = React.ComponentProps<"span"> & {
  color?: TimelineColor
}

function TimelineSeparator({
  className,
  color,
  ...props
}: TimelineSeparatorProps) {
  const context = useTimelineContext("TimelineSeparator")
  const resolvedColor = color ?? context.color

  return (
    <span
      data-slot="timeline-separator"
      data-color={resolvedColor}
      data-orientation={context.orientation}
      aria-hidden
      className={cn(
        separatorColorVariants({
          color: resolvedColor,
          orientation: context.orientation,
        }),
        className,
      )}
      {...props}
    />
  )
}

function TimelineBody({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const { orientation } = useTimelineContext("TimelineBody")

  return (
    <div
      data-slot="timeline-body"
      data-orientation={orientation}
      className={cn(
        "min-w-0 flex-1",
        orientation === "vertical"
          ? "pb-6 group-last/timeline-item:pb-0"
          : "pr-6 group-last/timeline-item:pr-0",
        className,
      )}
      {...props}
    />
  )
}

function TimelineTitle({
  className,
  ...props
}: React.ComponentProps<"h3">) {
  return (
    <h3
      data-slot="timeline-title"
      className={cn("text-sm font-semibold text-foreground", className)}
      {...props}
    />
  )
}

function TimelineTime({
  className,
  ...props
}: React.ComponentProps<"time">) {
  return (
    <time
      data-slot="timeline-time"
      className={cn("mt-0.5 block text-xs text-muted-foreground", className)}
      {...props}
    />
  )
}

function TimelineDescription({
  className,
  ...props
}: React.ComponentProps<"p">) {
  return (
    <p
      data-slot="timeline-description"
      className={cn(
        "mt-2 text-sm leading-relaxed text-muted-foreground",
        className,
      )}
      {...props}
    />
  )
}

export {
  Timeline,
  TimelineItem,
  TimelineHeader,
  TimelineIcon,
  TimelineSeparator,
  TimelineBody,
  TimelineTitle,
  TimelineTime,
  TimelineDescription,
}
export type {
  TimelineColor,
  TimelineOrientation,
  TimelineProps,
  TimelineIconProps,
  TimelineSeparatorProps,
}
