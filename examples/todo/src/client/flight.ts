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

async function navigate(url: string, push: boolean): Promise<void> {
  const root = document.getElementById('view-root')
  if (!root) {
    location.href = url
    return
  }

  try {
    const res = await fetch(url, { headers: { 'X-Flight': '1' } })
    const tree = (await res.json()) as unknown
    root.replaceChildren(toNode(tree))
    mountIslands(root)
    setActive(new URL(url).pathname)
    if (push) history.pushState({ flight: true }, '', url)
  } catch {
    location.href = url // fall back to a real navigation
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
    void navigate(link.href, true)
  })

  window.addEventListener('popstate', () => {
    void navigate(location.href, false)
  })
}
