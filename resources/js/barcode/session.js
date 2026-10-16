import { createDeviceSession } from '../media/capture/device-session.js'
import { nativeMediaOperation } from '../media/lifecycle/native-operation.js'
import { createBarcodeDecoder, startDecodeLoop } from './decoder/index.js'

export async function openBarcodeSession({ owner, video, configuration, deviceId, found, failed, ready, missing,
    devices = globalThis.navigator?.mediaDevices, decoderFactory = createBarcodeDecoder }) {
    const device = createDeviceSession({ owner, devices })
    const stream = await nativeMediaOperation(device.open({ audio: false, video: {
        ...(deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: configuration.cameraFacing } }),
        width: { ideal: 1280 }, height: { ideal: 720 },
    } }), owner, 30000)
    owner.assertActive()
    owner.defer(() => { video.pause(); video.srcObject = null })
    video.srcObject = stream
    await nativeMediaOperation(video.play(), owner)
    await owner.wait(() => video.readyState >= 2 && video.videoWidth > 0 && video.videoHeight > 0, {
        subscribe(probe) {
            const cleanup = ['loadeddata', 'canplay', 'playing', 'resize'].map(event => owner.listen(video, event, probe))
            return () => cleanup.forEach(release => release())
        },
    })
    const track = stream.getVideoTracks()[0]
    if (!track) throw Error('Camera returned no video track.')
    owner.listen(track, 'ended', () => failed(Error('Camera disconnected.')))
    const decoder = await decoderFactory({ video, formats: configuration.supportedFormats, owner })
    owner.assertActive()
    ready(track, decoder.engine)
    startDecodeLoop({ decoder, video, owner, interval: configuration.scanInterval, found, failed, missing })
    return track
}

