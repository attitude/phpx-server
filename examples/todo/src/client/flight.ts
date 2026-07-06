import { mountIslands } from './islands.tsx'

/**
 * Client-driven navigation over the Flight-style JSON endpoint.
 *
 * A click on an `<a data-flight-link>` fetches the target with an `X-Flight: 1`
 * header. The server responds with the serialized tuple tree of that view
 * (server components already resolved). We rebuild the DOM from it, swap it into
 * `#view-root`, mount any React islands it contains, and push history — no full
 * reload. With JS off, the same links are ordinary navigation.
 */

type Tuple = ['$', string, Record<string, unknown> | null | undefined, unknown?]

function isTuple(node: unknown): node is Tuple {
  return Array.isArray(node) && node[0] === '$'
}

function applyProps(el: HTMLElement, props: Record<string, unknown>): void {
  for (const [key, value] of Object.entries(props)) {
    if (value == null) continue
    if (key === 'dangerouslySetInnerHTML') {
      const html = (value as { __html?: unknown }).__html
      if (typeof html === 'string') el.innerHTML = html
      continue
    }
    if (typeof value === 'boolean') {
      if (value) el.setAttribute(key, '')
      continue
    }
    // className/style are already normalised to `class`/string by the server.
    el.setAttribute(key, String(value))
  }
}

function toNode(node: unknown): Node {
  if (node == null || typeof node === 'boolean') return document.createTextNode('')
  if (typeof node === 'string' || typeof node === 'number') return document.createTextNode(String(node))

  if (isTuple(node)) {
    const [, tag, props, children] = node

    if (tag === 'fragment') {
      const html = props?.dangerouslySetInnerHTML as { __html?: unknown } | undefined
      if (typeof html?.__html === 'string') {
        const tpl = document.createElement('template')
        tpl.innerHTML = html.__html
        return tpl.content
      }
      const frag = document.createDocumentFragment()
      if (children != null) frag.appendChild(toNode(children))
      return frag
    }

    const el = document.createElement(tag)
    if (props) applyProps(el, props)
    if (children != null) el.appendChild(toNode(children))
    return el
  }

  if (Array.isArray(node)) {
    const frag = document.createDocumentFragment()
    for (const child of node) frag.appendChild(toNode(child))
    return frag
  }

  return document.createTextNode('')
}

function setActive(pathname: string): void {
  document.querySelectorAll<HTMLAnchorElement>('a[data-flight-link]').forEach((a) => {
    a.classList.toggle('is-active', new URL(a.href).pathname === pathname)
  })
}

// Prefetch cache: hovering a flight link warms the Flight payload before the
// click lands, so the navigation below can await the already-inflight
// request instead of starting a fresh one.
const prefetchCache = new Map<string, Promise<unknown>>()

function prefetch(url: string): void {
  if (prefetchCache.has(url)) return
  prefetchCache.set(
    url,
    fetch(url, { headers: { 'X-Flight': '1' } }).then((res) => res.json())
  )
}

async function navigate(url: string, push: boolean): Promise<void> {
  const root = document.getElementById('view-root')
  if (!root) {
    location.href = url
    return
  }

  document.documentElement.setAttribute('data-flight-pending', '1')
  try {
    const cached = prefetchCache.get(url)
    prefetchCache.delete(url) // re-fetch next time, even on failure

    const tree = (await (cached ?? fetch(url, { headers: { 'X-Flight': '1' } }).then((res) => res.json()))) as unknown
    root.replaceChildren(toNode(tree))
    mountIslands(root)
    setActive(new URL(url).pathname)
    if (push) history.pushState({ flight: true }, '', url)
  } catch {
    location.href = url // fall back to a real navigation
  } finally {
    document.documentElement.removeAttribute('data-flight-pending')
  }
}

/**
 * Streaming Flight navigation: read the NDJSON payload row by row. The first
 * row is the shell (with fallback placeholders); each later row patches a
 * `#F:<n>` boundary as it resolves — out of order, as they arrive.
 */
export async function streamNavigate(url: string, push = true): Promise<void> {
  const root = document.getElementById('view-root')
  if (!root) {
    location.href = url
    return
  }

  document.documentElement.setAttribute('data-flight-pending', '1')
  try {
    const res = await fetch(url, { headers: { 'X-Flight-Stream': '1' } })
    if (!res.body) throw new Error('no stream')

    const reader = res.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''
    let first = true

    for (;;) {
      const { done, value } = await reader.read()
      if (done) break
      buffer += decoder.decode(value, { stream: true })

      let nl: number
      while ((nl = buffer.indexOf('\n')) >= 0) {
        const line = buffer.slice(0, nl).trim()
        buffer = buffer.slice(nl + 1)
        if (!line) continue

        const msg = JSON.parse(line) as unknown
        if (first) {
          root.replaceChildren(toNode(msg))
          mountIslands(root)
          setActive(new URL(url).pathname)
          if (push) history.pushState({ flight: true }, '', url)
          first = false
          // The shell (with fallback placeholders) is now visible — the point
          // the user perceives the navigation as done, even if boundaries are
          // still streaming in.
          document.documentElement.removeAttribute('data-flight-pending')
        } else {
          const row = msg as { b: number; tree: unknown }
          const slot = document.getElementById('F:' + row.b)
          if (slot) {
            slot.replaceChildren(toNode(row.tree))
            mountIslands(slot)
          }
        }
      }
    }
  } catch {
    location.href = url
  } finally {
    document.documentElement.removeAttribute('data-flight-pending')
  }
}

export function initFlight(): void {
  document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return
    }
    const target = event.target as HTMLElement | null
    const link = target?.closest?.('a[data-flight-link]') as HTMLAnchorElement | null
    if (!link || link.origin !== location.origin) return

    event.preventDefault()
    // Links marked data-flight-stream use the streaming (NDJSON) transport so
    // Suspense boundaries in the target view arrive progressively.
    if (link.hasAttribute('data-flight-stream')) {
      void streamNavigate(link.href, true)
    } else {
      void navigate(link.href, true)
    }
  })

  // Prefetch on hover: warm the Flight payload as soon as the pointer enters
  // a link, so the click above often just awaits an already-inflight fetch.
  document.addEventListener('mouseover', (event) => {
    const target = event.target as HTMLElement | null
    const link = target?.closest?.('a[data-flight-link]') as HTMLAnchorElement | null
    if (!link || link.origin !== location.origin) return

    prefetch(link.href)
  })

  window.addEventListener('popstate', () => {
    void navigate(location.href, false)
  })
}
