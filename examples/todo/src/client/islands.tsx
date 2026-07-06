import { createRoot, type Root } from 'react-dom/client'
import type { ReactElement } from 'react'
import TodoApp, { type Todo } from './TodoApp.tsx'

// The client-component registry: the names PHP's Client('Name', props) can
// reference. Each maps JSON props to a React element.
const registry: Record<string, (props: Record<string, unknown>) => ReactElement> = {
  TodoApp: (props) => <TodoApp initialTodos={(props.todos as Todo[]) ?? []} />,
}

const roots = new WeakMap<Element, Root>()

/**
 * Find every [data-client] boundary under `root`, read its JSON props, and
 * mount the matching React component. Idempotent per element, so it's safe to
 * call again after Flight navigation injects new boundaries.
 */
export function mountIslands(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>('[data-client]').forEach((el) => {
    if (roots.has(el)) return

    const name = el.getAttribute('data-client')
    const make = name ? registry[name] : undefined
    if (!make) return

    const props = JSON.parse(el.getAttribute('data-props') ?? '{}') as Record<string, unknown>
    const root = createRoot(el)
    roots.set(el, root)
    root.render(make(props))
  })
}
