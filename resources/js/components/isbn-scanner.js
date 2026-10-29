import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { makeScannerComponent } from '../scanner/component.js';

export default makeScannerComponent((id) => new Html5Qrcode(id, {
    formatsToSupport: [Html5QrcodeSupportedFormats.EAN_13],
    experimentalFeatures: { useBarCodeDetectorIfSupported: false },
    verbose: false,
}));
