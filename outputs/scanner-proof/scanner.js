'use strict';
const $ = (id) => document.getElementById(id);
let scanner = null;
let running = false;
let starting = false;
let accepting = false;
let stopping = null;
const checks = [];
let decodingPhoto = false;
function status(message) { $('status').textContent = message; }
function controls() {
    $('start').disabled = running || starting || Boolean(stopping) || decodingPhoto;
    $('stop').disabled = !running || Boolean(stopping);
    $('next').disabled = starting || Boolean(stopping) || decodingPhoto;
    $('photo').disabled = starting || Boolean(stopping) || decodingPhoto;
}
async function stopCamera() {
    accepting = false;
    if (stopping) return stopping;
    if (!running) return;
    stopping = (async () => {
        try { await scanner.stop(); running = false; }
        catch { status('Camera could not stop cleanly. Close the tab to release it.'); }
        finally { stopping = null; controls(); }
    })();
    controls();
    return stopping;
}
async function accept(value, source) {
    const isbn = WiseOwlIsbn.canonicalIsbn(value);
    if (!isbn) { status('Invalid book ISBN. Check the digits or include the entire barcode.'); return; }
    if (source === 'camera' && !accepting) return;
    accepting = false;
    await stopCamera();
    const duplicate = checks.some((check) => check.isbn === isbn);
    checks.push({ isbn, source, repeatedInTest: duplicate, time: new Date().toISOString() });
    $('canonical').textContent = isbn;
    $('detail').textContent = duplicate ? 'Seen earlier in this test. Intake will prompt Modify / Add more / Cancel.' : 'Valid checksum. ISBN-10 is normalised to ISBN-13.';
    $('result').hidden = false;
    $('isbn').value = '';
    $('empty').hidden = true;
    const row = document.createElement('li');
    row.textContent = `${isbn} · ${source}${duplicate ? ' · repeated' : ''}`;
    $('history').prepend(row);
    $('export').disabled = false;
    status('ISBN recognised. No stock received.');
    $('result').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
async function startCamera() {
    if (starting || running || stopping || decodingPhoto) return;
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        status('Camera access needs a supported browser and trusted HTTPS, or desktop localhost. Use manual input below.'); return;
    }
    if (!window.Html5Qrcode) { status('Scanner library did not load. Check your connection or enter the ISBN manually.'); return; }
    starting = true; controls(); $('result').hidden = true;
    status('Requesting rear camera access…');
    try {
        if (!scanner) scanner = new Html5Qrcode('reader', {
            formatsToSupport: [Html5QrcodeSupportedFormats.EAN_13],
            experimentalFeatures: { useBarCodeDetectorIfSupported: false }, verbose: false,
        });
        await scanner.start({ facingMode: 'environment' }, {
            fps: 10,
            qrbox: (w, h) => ({ width: Math.floor(Math.min(w * .9, 360)), height: Math.floor(Math.min(h * .65, 160)) }),
        }, (value) => { if (accepting) void accept(value, 'camera'); }, () => {});
        running = true; accepting = true;
        status('Camera ready. Hold the full book barcode inside the guide.');
        if (document.hidden) await stopCamera();
    } catch { status('Camera could not start. Check camera permission and HTTPS, or enter the ISBN manually.'); }
    finally { starting = false; controls(); }
}
$('start').addEventListener('click', startCamera);
$('next').addEventListener('click', startCamera);
$('stop').addEventListener('click', async () => { await stopCamera(); if (!running) status('Camera stopped. Start again or enter an ISBN.'); });
$('manual').addEventListener('submit', (event) => {
    event.preventDefault();
    if (starting || stopping) { status('Wait for the camera to finish starting or stopping.'); return; }
    void accept($('isbn').value, 'manual');
});
$('example').addEventListener('click', () => { $('isbn').value = '9780140328721'; $('isbn').focus(); });
$('photo').addEventListener('change', async () => {
    const file = $('photo').files?.[0];
    if (!file || starting || stopping || decodingPhoto) return;
    if (!window.Html5Qrcode) { status('Scanner library did not load. Check your connection or enter the ISBN manually.'); $('photo').value = ''; return; }
    decodingPhoto = true; controls();
    try {
        await stopCamera();
        if (running) return;
        if (!scanner) scanner = new Html5Qrcode('reader', { formatsToSupport: [Html5QrcodeSupportedFormats.EAN_13], verbose: false });
        status('Reading barcode photo on this device…');
        const value = await scanner.scanFile(file, false);
        await accept(value, 'photo');
    } catch { status('Could not read a book barcode in that photo. Try a sharper, closer photo including the whole barcode, or enter the ISBN.'); }
    finally { decodingPhoto = false; $('photo').value = ''; controls(); }
});
document.addEventListener('visibilitychange', () => { if (document.hidden) void stopCamera(); });
window.addEventListener('pagehide', () => { void stopCamera(); });
$('export').addEventListener('click', () => {
    const report = { experiment: 'Wise Owl ISBN proof — not inventory', userAgent: navigator.userAgent, checks };
    const url = URL.createObjectURL(new Blob([JSON.stringify(report, null, 2)], { type: 'application/json' }));
    const link = document.createElement('a'); link.href = url; link.download = 'wise-owl-scanner-test.json'; link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
});
