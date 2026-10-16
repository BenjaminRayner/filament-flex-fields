/** Pack visible cells into measurable grid rows; section headers occupy a full row. */
export function buildGridRowProjection(flatRows, { columns = 4, gap = 10, estimateHeight = () => 96 } = {}) {
    const width = Math.max(1, Math.floor(columns))
    const rows = []
    const positions = new Map()
    let cells = []
    const flush = () => {
        if (!cells.length) return
        const indexes = cells
        const rowIndex = rows.length
        for (const index of indexes) positions.set(index, rowIndex)
        rows.push({ key: `grid:${flatRows[indexes[0]].key}`, indexes,
            height: Math.max(...indexes.map((index) => estimateHeight(flatRows[index]))) })
        cells = []
    }
    flatRows.forEach((row, index) => {
        if (row.type === 'section' || row.type === 'group-header' || row.type === 'separator') {
            flush()
            positions.set(index, rows.length)
            rows.push({ key: `grid-full:${row.key}`, indexes: [index], height: estimateHeight(row) })
            return
        }
        cells.push(index)
        if (cells.length === width) flush()
    })
    flush()
    return { rows, positions, sizeForIndex: (index) => rows[index].height + (index < rows.length - 1 ? gap : 0) }
}
