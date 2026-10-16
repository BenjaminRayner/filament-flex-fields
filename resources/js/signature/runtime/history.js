/** Immutable stroke snapshots: undo/redo also restore a cleared signature. */
export function createSignatureHistory(owner) {
    let replayFrame = null, wipe = null, wipeGeneration = 0
    const reduced = () => globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    return {
        get canUndo() { return this.undoable && this.undoStack.length > 0 && !this.readOnly && !this.isDrawing },
        get canRedo() { return this.undoable && this.redoStack.length > 0 && !this.readOnly && !this.isDrawing },
        get canClear() { return this.hasSignature && !this.readOnly && !this.isDrawing },
        get canReplay() { return this.replayEnabled && this.hasSignature && !this.isDrawing && this.strokes.every(stroke => !stroke.outline && stroke.points.length > 0) },
        rememberHistory(previous = this.strokes) {
            if (!this.undoable) return
            this.undoStack.push([...previous])
            if (this.undoStack.length > 60) this.undoStack.shift()
            this.redoStack = []
        },
        undo() {
            if (!this.canUndo) return
            this.stopReplay(); this.stopWipe()
            this.redoStack.push([...this.strokes]); this.strokes = this.undoStack.pop()
            this.commitState(); this.scheduleRedraw()
        },
        redo() {
            if (!this.canRedo) return
            this.stopReplay(); this.stopWipe()
            this.undoStack.push([...this.strokes]); this.strokes = this.redoStack.pop()
            this.commitState(); this.scheduleRedraw()
        },
        clear() {
            if (!this.canClear) return
            this.stopReplay(); this.stopWipe(); this.rememberHistory()
            const canvas = this.activeCanvas
            this.strokes = []; this.currentStroke = null; this.inkTrail = []
            this.commitState()
            if (!canvas?.animate || reduced()) { this.scheduleRedraw(); return }
            this.isClearing = true
            const generation = ++wipeGeneration
            wipe = canvas.animate([{ clipPath: 'inset(0 0 0 0)' }, { clipPath: 'inset(0 0 0 100%)' }], { duration: 480, easing: 'cubic-bezier(.22,1,.36,1)' })
            wipe.finished.catch(() => {}).finally(() => {
                if (owner.disposed || generation !== wipeGeneration) return
                wipe = null
                this.isClearing = false; this.scheduleRedraw()
            })
        },
        stopWipe() { wipeGeneration++; wipe?.cancel(); wipe = null; this.isClearing = false },
        selectPenColor(color) {
            if (this.readOnly || this.isDrawing || !this.penColors.includes(color)) return
            this.penColor = color
        },
        selectPenWidth(width) {
            if (this.readOnly || this.isDrawing || !this.penWidths.includes(width)) return
            this.penWidth = width
        },
        handleShortcut(event) {
            if (this.isTypingTarget(event.target) || this.readOnly) return
            const key = event.key.toLowerCase()
            if ((event.metaKey || event.ctrlKey) && key === 'z') { event.preventDefault(); event.shiftKey ? this.redo() : this.undo() }
            else if ((event.metaKey || event.ctrlKey) && key === 'y') { event.preventDefault(); this.redo() }
            else if (key === 'delete' || key === 'backspace') { event.preventDefault(); this.clear() }
            else if (key === 'escape') this.stopReplay()
        },
        replay() {
            if (!this.canReplay || owner.disposed) return
            if (this.isReplaying) { this.stopReplay(); return }
            this.stopWipe()
            if (reduced()) { this.scheduleRedraw(); return }
            const strokes = [...this.strokes], total = strokes.reduce((sum, stroke) => sum + stroke.points.length, 0)
            if (!total) { this.scheduleRedraw(); return }
            const duration = Math.min(3200, Math.max(600, total * 5))
            let started = null
            this.isReplaying = true
            const draw = now => {
                if (owner.disposed || !this.isReplaying) return
                started ??= now
                let remaining = Math.floor(Math.min(1, (now - started) / duration) * total)
                this.replayStrokes = strokes.map(stroke => {
                    const count = Math.min(stroke.points.length, Math.max(0, remaining)); remaining -= count
                    return { ...stroke, path: undefined, points: stroke.points.slice(0, count) }
                })
                this.redraw()
                if (now - started >= duration) this.stopReplay()
                else replayFrame = requestAnimationFrame(draw)
            }
            replayFrame = requestAnimationFrame(draw)
        },
        stopReplay() {
            if (replayFrame !== null) cancelAnimationFrame(replayFrame)
            replayFrame = null; this.isReplaying = false; this.replayStrokes = null
            if (!owner.disposed) this.scheduleRedraw()
        },
    }
}
