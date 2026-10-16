import { publishOwnerData } from '../../runtime/alpine/owner-data.js'
import { readPreviewFile } from '../../media/upload/preview-file.js'
import { cameraState } from './camera-state.js'

/** Source adapters share transport and ownership; they never persist permanent media. */
export function createUploadSources({ owner, getPond, config }) {
    let camera = null, cameraLoad = null, generation = 0

    return {
        urlValue: '', urlImporting: false, urlError: null,
        ...cameraState(config),

        sourceError(error, fallback) {
            const errors = error?.response?.data?.errors ?? error?.errors
            const first = errors && Object.values(errors).flat().find(value => typeof value === 'string' && value)
            return first || config.labels?.[error?.reason] || fallback
        },

        async importFromUrl() {
            if (config.isDisabled || this.urlImporting || !config.allowUrlUpload) return
            const url = this.urlValue.trim()
            if (!url) { this.urlError = config.labels?.urlRequired ?? 'Enter a file URL.'; return }
            this.urlImporting = true
            this.urlError = null
            let stagingFilename = null
            try {
                const payload = await this.$wire.callSchemaComponentMethod(config.schemaComponentKey, 'importUploadedFileFromUrl', { url })
                stagingFilename = payload?.stagingFilename
                owner.assertActive()
                const file = await readPreviewFile(payload, { owner, maxBytes: config.maxBytes })
                this.select('file')
                await getPond().add(file, 'url')
                owner.assertActive()
                this.urlValue = ''
            } catch (error) {
                if (!owner.disposed) this.urlError = this.sourceError(error, config.labels?.urlFetchFailed ?? 'Unable to import this file.')
            } finally {
                // Native TTL cleanup remains authoritative if navigation interrupts this request.
                if (stagingFilename && !owner.disposed) {
                    try { await this.$wire.callSchemaComponentMethod(config.schemaComponentKey, 'discardAlternateSourceStagingUpload', { stagingFilename }) }
                    catch { /* Temporary upload is still covered by native expiry. */ }
                }
                if (!owner.disposed) this.urlImporting = false
            }
        },

        async openWebcamModal() {
            if (config.isDisabled || !config.allowWebcamUpload || owner.disposed) return
            const attempt = ++generation
            cameraLoad ??= import('./camera.js').catch(error => { cameraLoad = null; throw error })
            try {
                const { createUploadCamera } = await cameraLoad
                owner.assertActive()
                if (attempt !== generation) return
                if (!camera) {
                    camera = createUploadCamera({ owner, getPond, config })
                    publishOwnerData(this, camera)
                    owner.defer(() => camera.closeWebcamModal.call(this))
                }
                await camera.openWebcamModal.call(this)
            } catch (error) { if (!owner.disposed) this.actionError = this.sourceError(error, config.labels?.webcamUnavailable) }
        },
        closeWebcamModal() { ++generation; camera?.closeWebcamModal.call(this) },
    }
}
