export function createMatrixPresentation(root) {
    const cells = [...root.querySelectorAll('[data-matrix-row]')].map(element => ({
        element,row:Number(element.dataset.matrixRow),col:Number(element.dataset.matrixCol),
        rowKey:element.dataset.rowKey,columnKey:element.dataset.columnKey,input:element.querySelector('input'),disabled:false,
    }))
    const rows = [...root.querySelectorAll('[data-matrix-row-key]')]
    let focused = cells.find(cell => cell.element.tabIndex === 0) ?? null
    return {
        cells,
        render(view,disabled) {
            root.classList.toggle('is-disabled',disabled)
            for (const row of rows) row.classList.toggle('is-disabled',disabled || view.blockedRows.has(row.dataset.matrixRowKey))
            for (const cell of cells) {
                const checked = view.selected.get(cell.rowKey)?.has(cell.columnKey) ?? false
                cell.disabled = !view.canChange(cell.rowKey,cell.columnKey)
                cell.element.classList.toggle('is-selected',checked)
                cell.element.classList.toggle('is-disabled',cell.disabled)
                if (cell.element.getAttribute('aria-selected') !== String(checked)) cell.element.setAttribute('aria-selected',String(checked))
                if (cell.element.getAttribute('aria-disabled') !== String(cell.disabled)) cell.element.setAttribute('aria-disabled',String(cell.disabled))
                if (cell.input.checked !== checked) cell.input.checked=checked
                if (cell.input.disabled !== cell.disabled) cell.input.disabled=cell.disabled
            }
            if (!focused || focused.disabled || !cells.includes(focused)) focused=cells.find(cell => !cell.disabled)
            for (const cell of cells) if (cell.element.tabIndex !== (cell === focused ? 0 : -1)) cell.element.tabIndex=cell === focused ? 0 : -1
        },
        focus(cell) {
            if (!cell || cell.disabled) return
            if (focused) focused.element.tabIndex=-1
            focused=cell;cell.element.tabIndex=0;cell.element.focus()
        },
        fromEvent(event) {
            const element = event.target.closest?.('[data-matrix-row]')
            return cells.find(cell => cell.element === element)
        },
    }
}
