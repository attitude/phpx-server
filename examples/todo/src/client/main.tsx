import { createRoot } from 'react-dom/client'
import TodoApp from './TodoApp.tsx'
import type { Todo } from './TodoApp.tsx'
import './todo.css'

const mount = document.querySelector<HTMLElement>('[data-client="TodoApp"]')

if (mount) {
  const props = JSON.parse(mount.getAttribute('data-props') ?? '{}') as { todos: Todo[] }
  createRoot(mount).render(<TodoApp initialTodos={props.todos ?? []} />)
}
