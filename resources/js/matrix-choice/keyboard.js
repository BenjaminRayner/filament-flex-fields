/** Spatial navigation follows visual direction and never traps Tab inside the grid. */
export function matrixDestination(cells, current, key, rtl=false) {
    const enabled = cells.filter(cell => !cell.disabled)
    const row = current.row, col = current.col
    if (key === 'Home' || key === 'End') {
        const line = enabled.filter(cell => cell.row === row)
        return (key === 'Home' ? line[0] : line.at(-1)) ?? null
    }
    const horizontal = key === 'ArrowLeft' || key === 'ArrowRight'
    const delta = key === 'ArrowDown' ? 1 : key === 'ArrowUp' ? -1 : key === 'ArrowRight' ? (rtl ? -1 : 1) : (rtl ? 1 : -1)
    const candidates = enabled.filter(cell => horizontal ? cell.row === row && (cell.col-col)*delta > 0 : cell.col === col && (cell.row-row)*delta > 0)
    candidates.sort((a,b) => horizontal ? Math.abs(a.col-col)-Math.abs(b.col-col) : Math.abs(a.row-row)-Math.abs(b.row-row))
    return candidates[0] ?? null
}
