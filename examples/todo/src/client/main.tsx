import './todo.css'
import { mountIslands } from './islands.tsx'
import { initFlight } from './flight.ts'

// Boot any islands rendered in the initial HTML, then enable Flight navigation.
mountIslands(document)
initFlight()
