/** Actions describe this file's current native state, never a page-wide capability. */
export function fileMenuActions(file, config, { canEditImage, queuePaused }) {
    if (!file || file.leaving) return []
    const actions = []
    if (file.complete && !file.failed) {
        if (config.hasFileViewer && file.preview) actions.push('viewer')
        if (config.hasFileMetadataEditor && !config.isDisabled) actions.push('metadata')
        if (canEditImage) actions.push('edit')
        if (config.isOpenable && file.openUrl) actions.push('open')
        if (config.isDownloadable && file.downloadUrl) actions.push('download')
    }
    if (!config.isDisabled) {
        if (file.failed && !queuePaused) actions.push('retry')
        if (config.isPausable && file.uploading && !file.paused && !queuePaused) actions.push('pause')
        if (config.isPausable && file.paused && !queuePaused) actions.push('resume')
        if (config.instantUpload === false && !file.complete && !file.failed && !file.uploading && !file.loading && !file.paused && !queuePaused) actions.push('process')
        if (file.uploading) actions.push('cancel')
        else if (config.isDeletable !== false) actions.push('remove')
    }
    return actions
}
