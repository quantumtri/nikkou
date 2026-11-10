// Barcode reading: the native BarcodeDetector where it exists (Chrome, Android),
// otherwise ZXing, loaded on demand (Safari on iOS has no BarcodeDetector).
const FORMATS = ['qr_code', 'code_128'];

let zxingReader = null;

async function zxing() {
    if (!zxingReader) {
        const { BrowserMultiFormatReader } = await import('@zxing/browser');
        zxingReader = new BrowserMultiFormatReader();
    }

    return zxingReader;
}

const hasNative = () => 'BarcodeDetector' in window;

export const cameraSupported = () => Boolean(navigator.mediaDevices?.getUserMedia) && window.isSecureContext;

/** Read a code from an image file (photo or gallery). Resolves to the text or null. */
export async function decodeFile(file) {
    if (hasNative()) {
        const bitmap = await createImageBitmap(file);
        const results = await new BarcodeDetector({ formats: FORMATS }).detect(bitmap);
        bitmap.close?.();

        return results[0]?.rawValue ?? null;
    }

    const url = URL.createObjectURL(file);

    try {
        return (await (await zxing()).decodeFromImageUrl(url)).getText();
    } catch {
        return null;
    } finally {
        URL.revokeObjectURL(url);
    }
}

/**
 * Start live scanning on a <video> element. Calls onCode once per detected
 * code and returns a stop() function that releases the camera.
 */
export async function startCamera(video, onCode) {
    if (!hasNative()) {
        const controls = await (
            await zxing()
        ).decodeFromConstraints({ video: { facingMode: 'environment' } }, video, (result) => {
            if (result) onCode(result.getText());
        });

        return () => controls.stop();
    }

    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    video.srcObject = stream;
    await video.play();

    const detector = new BarcodeDetector({ formats: FORMATS });
    let active = true;

    const tick = async () => {
        if (!active) return;

        try {
            const results = video.readyState >= 2 ? await detector.detect(video) : [];
            if (results[0]) onCode(results[0].rawValue);
        } catch {
            // A frame that cannot be read is not an error; keep scanning.
        }

        if (active) setTimeout(tick, 250);
    };

    tick();

    return () => {
        active = false;
        stream.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    };
}
