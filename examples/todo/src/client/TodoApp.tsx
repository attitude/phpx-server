import { useRef, useState, type FormEvent } from 'react'

export type Todo = { id: string; text: string; done: boolean }

type ActionId = 'todo/add' | 'todo/toggle' | 'todo/delete' | 'todo/clearCompleted'

async function callAction(id: ActionId, args: Record<string, unknown>): Promise<Todo[]> {
  const res = await fetch(window.location.pathname, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, args }),
  })
  const data = await res.json()
  if (typeof data.__redirect === 'string') {
    window.location.href = data.__redirect // hard navigation, page unloads
    return []
  }
  return data.todos as Todo[]
}

type Filter = 'all' | 'active' | 'completed'

export default function TodoApp({ initialTodos }: { initialTodos: Todo[] }) {
  const [todos, setTodos] = useState<Todo[]>(initialTodos)
  const [text, setText] = useState('')
  const [filter, setFilter] = useState<Filter>('all')
  const snapshot = useRef<Todo[]>(initialTodos)
  const tempId = useRef(0)

  async function run(action: ActionId, args: Record<string, unknown>) {
    try {
      const fresh = await callAction(action, args)
      setTodos(fresh)
    } catch (err) {
      console.error(`${action} failed`, err)
      setTodos(snapshot.current)
    }
  }

  function handleAdd(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    const value = text.trim()
    if (!value) return

    snapshot.current = todos
    setText('')
    tempId.current += 1
    setTodos([...todos, { id: `tmp-${tempId.current}`, text: value, done: false }])
    run('todo/add', { text: value })
  }

  function handleToggle(id: string) {
    snapshot.current = todos
    setTodos(todos.map((t) => (t.id === id ? { ...t, done: !t.done } : t)))
    run('todo/toggle', { id })
  }

  function handleDelete(id: string) {
    snapshot.current = todos
    setTodos(todos.filter((t) => t.id !== id))
    run('todo/delete', { id })
  }

  function handleClearCompleted() {
    snapshot.current = todos
    setTodos(todos.filter((t) => !t.done))
    run('todo/clearCompleted', {})
  }

  const visible = todos.filter((t) => {
    if (filter === 'active') return !t.done
    if (filter === 'completed') return t.done
    return true
  })
  const remaining = todos.filter((t) => !t.done).length
  const hasCompleted = todos.some((t) => t.done)

  return (
    <div className="TodoAppView">
      <form className="AddFormView" onSubmit={handleAdd}>
        <input
          className="AddFormInput"
          type="text"
          value={text}
          onChange={(e) => setText(e.target.value)}
          placeholder="Add a todo…"
          autoComplete="off"
        />
        <button type="submit" className="AddFormButton">
          Add
        </button>
      </form>

      {visible.length === 0 ? (
        <p className="TodoEmptyText">Nothing to do. Add something above.</p>
      ) : (
        <ul className="TodoListView">
          {visible.map((todo) => (
            <li key={todo.id} className={`TodoItemView${todo.done ? ' is-done' : ''}`}>
              <button
                type="button"
                className="TodoToggleButton"
                aria-pressed={todo.done}
                onClick={() => handleToggle(todo.id)}
              >
                {todo.done ? '☑' : '☐'}
              </button>
              <span className="TodoItemText">{todo.text}</span>
              <button
                type="button"
                className="TodoDeleteButton"
                aria-label={`Delete "${todo.text}"`}
                onClick={() => handleDelete(todo.id)}
              >
                ✕
              </button>
            </li>
          ))}
        </ul>
      )}

      <footer className="TodoFooterView">
        <span className="TodoCountText">
          {remaining} {remaining === 1 ? 'item' : 'items'} left
        </span>

        <div className="TodoFiltersView">
          {(['all', 'active', 'completed'] as const).map((f) => (
            <button
              key={f}
              type="button"
              className={`TodoFilterButton${filter === f ? ' is-active' : ''}`}
              onClick={() => setFilter(f)}
            >
              {f[0].toUpperCase() + f.slice(1)}
            </button>
          ))}
        </div>

        <button
          type="button"
          className="TodoClearButton"
          onClick={handleClearCompleted}
          disabled={!hasCompleted}
        >
          Clear completed
        </button>
      </footer>
    </div>
  )
}
