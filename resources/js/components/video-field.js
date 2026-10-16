import { createVideoPlayer } from '../video/player.js'
export { formatVideoTime, formatPlaybackRateLabel, resolveDefaultQualityKey, resolveCoarsePointer, resolveProgressHoverRatio, resolveScrubDuration } from '../video/values.js'
export default function videoFieldFormComponent(options) { return createVideoPlayer(options) }
