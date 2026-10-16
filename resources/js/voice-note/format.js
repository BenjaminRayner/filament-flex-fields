import { formatAudioTime } from '../core/format-time.js'
export function formatTime(seconds) {
    return formatAudioTime(seconds)
}

/**
 * Map MediaRecorder MIME types to extensions accepted by fill validation.
 * audio/webm → webm (not "webm;codecs=opus"), audio/mp4 → m4a (Safari).
 */
export function voiceNoteExtensionForMime(mimeType) {
    const normalized = String(mimeType || '')
        .split(';')[0]
        .trim()
        .toLowerCase()

    const map = {
        'audio/webm': 'webm',
        'video/webm': 'webm',
        'audio/mp4': 'm4a',
        'video/mp4': 'm4a',
        'audio/mpeg': 'mp3',
        'audio/mp3': 'mp3',
        'audio/wav': 'wav',
        'audio/x-wav': 'wav',
        'audio/ogg': 'ogg',
        'audio/aac': 'aac',
        'audio/x-m4a': 'm4a',
    }

    if (map[normalized]) {
        return map[normalized]
    }

    const subtype = normalized.includes('/')
        ? normalized.split('/')[1]
        : normalized

    if (subtype === 'mp4') {
        return 'm4a'
    }

    return subtype && /^[a-z0-9]+$/i.test(subtype) ? subtype : 'webm'
}

