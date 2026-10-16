import test from 'node:test'
import assert from 'node:assert/strict'
import { animateNativeTodoStrike, cancelNativeTodoMotion, strikeProgress } from '../../resources/js/components/todo-list-field/motion-native.js'

function fixture() {
    const animations = [], writes = []
    const node = name => ({ animate(frames, options) {
        let finish, reject
        const animation = { name, frames, options, currentTime: 0, cancelled: false,
            finished: new Promise((resolve, failure) => { finish = resolve; reject = failure }),
            finish() { finish() }, cancel() { this.cancelled = true; this.currentTime = null; reject(Error('cancelled')) },
        }
        // Equivalent to native finished promises: test fixture also consumes cancelled spark promises.
        animation.finished.catch(() => {})
        animations.push(animation); return animation
    } })
    const title = node('title'), strike = node('strike'), spark = node('spark')
    const row = { isConnected: true, style: { setProperty(key, value) { writes.push([key, value]) } },
        querySelector(selector) { return selector.includes('title-wrap') ? title : spark },
        querySelectorAll() { return [strike] },
    }
    return { row, animations, writes }
}

test('native motion preserves the strike/nudge and spark timings without per-frame row writes', async () => {
    const f = fixture(); let completed = 0
    assert.equal(animateNativeTodoStrike(f.row, { on: true, onComplete() { completed++ } }), true)
    assert.deepEqual(f.writes, [])
    assert.equal(f.animations[0].options.duration, 300)
    assert.equal(f.animations[1].frames[1].offset, 0.5)
    assert.equal(f.animations[2].options.delay, 200)
    assert.equal(f.animations[2].options.duration, 200)
    f.animations[0].currentTime = 75
    assert.equal(strikeProgress(f.row), 0.5)
    f.animations[0].finish(); await Promise.resolve()
    assert.equal(completed, 1)
    assert.equal(strikeProgress(f.row), 1)
    assert.deepEqual(f.writes, [['--text-line-scale', '1'], ['--text-x', '0px']])
    f.animations[2].finish(); await Promise.resolve()
    assert.equal(strikeProgress(f.row), null)
})

test('interrupting a strike cancels only its owner and preserves partial progress for reverse motion', async () => {
    const f = fixture(); let completed = 0
    animateNativeTodoStrike(f.row, { on: true, onComplete() { completed++ } })
    f.animations[0].currentTime = 60
    const from = strikeProgress(f.row)
    cancelNativeTodoMotion(f.row)
    assert.equal(from, 0.4)
    assert.ok(f.animations.every(animation => animation.cancelled))
    await Promise.resolve()
    assert.equal(completed, 0)
    animateNativeTodoStrike(f.row, { on: false, from })
    const clock = f.animations[3]
    assert.equal(clock.options.duration, 250)
    clock.currentTime = 125
    assert.equal(strikeProgress(f.row), 0.2)
    assert.equal(f.animations[4].frames[0].clipPath, 'inset(-3px 60% -3px 0)')
    cancelNativeTodoMotion(f.row)
})

test('unsupported native animation falls back without allocating animation owners', () => {
    const row = { querySelector() { return null } }
    assert.equal(animateNativeTodoStrike(row, { on: true }), false)
    assert.equal(strikeProgress(row), null)
    cancelNativeTodoMotion(row)
})
