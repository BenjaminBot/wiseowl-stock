# Scanning proof and device test sheet

The [standalone experiment](../outputs/scanner-proof/index.html) tests ISBN identification before Filament intake. It is not the stock application: no database, users, receipts, quantities, pricing, cover lookup, or cross-device persistence. Repeated checks are labelled; the real Modify/Add workflow is specified, not implemented here.

The proof uses html5-qrcode 2.3.8 from jsDelivr with EAN-13 enabled and the non-native decoder path. Review maintenance/licensing and select a production dependency during implementation. Manual input validates ISBN-10/13 checksums and normalises ISBN-10 to ISBN-13. Test results are memory-only, exportable as JSON, and disappear on reload. Frames are decoded on-device; there are no metadata-provider requests.

## Desktop review

Open `outputs/scanner-proof/index.html` in a browser. Choose Use example then Check ISBN. Repeat to see the duplicate indication. Try an invalid checksum and ISBN-10 `0-14-032872-6` (the same canonical book). If camera access is blocked for local files, use localhost.

When Python is available, a static server can be started from the repository root:

```sh
python -m http.server 8765 --bind 127.0.0.1
```

Open `http://localhost:8765/outputs/scanner-proof/`. Stop with Ctrl+C when finished. This address is for the computer only, not the phone.

## iPhone test

Serve the directory through HTTPS with a certificate trusted by the iPhone. A plain HTTP LAN IP is insufficient. No phone-accessible deployment or HTTPS setup is configured yet. Use an approved test host/local HTTPS setup; do not bypass certificate warnings or disable browser security.

Open Safari, tap Start camera, and allow permission. Include the whole barcode. On detection, decoding should stop and a single result appear. Scan next starts a new capture explicitly. Backgrounding/navigation should release the camera; verify its indicator.

Record model, iOS/browser version, connection, ISBN, approximate detection time, failed/false reads, and notes. Test at least ten representative books including glossy/worn labels and Maltese titles. Camera decoding is independent from metadata coverage.

| Check | Expected | Actual |
| --- | --- | --- |
| HTTPS and permission | Rear camera starts after tap | Pending device test |
| Ordinary 978 barcode | One canonical ISBN | Pending device test |
| Valid 979 book barcode | Recognised | Pending device test |
| Repeated camera frames | One result per capture | Pending device test |
| Scan same book again | Explicit repeated-test indication | Pending device test |
| Non-book EAN | Rejected as ISBN | Pending device test |
| Denied permission | Helpful status; manual entry usable | Pending device test |
| Stop/background/return | Camera released; restart works | Pending device test |
| Narrow screen/keyboard | Controls reachable without sideways scrolling | Pending device test |
| CDN unavailable | Camera error; manual entry works | Pending device test |
| Export results | JSON report available | Pending device test |

Do not mark Phase 1 complete or claim iPhone support verified until real-device results and workflow review are recorded.

References: [scanner API](https://scanapp.org/html5-qrcode-docs/docs/intro), [supported formats](https://scanapp.org/html5-qrcode-docs/docs/supported_code_formats), [camera secure contexts](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).

## Temporary LAN preview (2026-10-28)

The experiment is served at http://192.168.0.225:8765/ while the host process is running. Connect the iPhone to the same LAN/Wi-Fi. This address is specific to the current computer/network and can change. Only the demo HTML/JavaScript and this test sheet are exposed; repository browsing is disabled.

HTTP does not enable live camera access. The added Take a barcode photo input uses the phone's photo capture/file picker and decodes locally. The actual phone/photo-decoder path still needs device validation. Manual ISBN input remains available.

Windows Firewall was not changed: automatic approval review rejected a persistent inbound rule. Access from another device may therefore be blocked even when the host can open the LAN address. A narrowly scoped test rule needs explicit approval if required.

The temporary Python process can be stopped using the server PID reported in chat. Do not stop unrelated Python processes. No public tunnel or port-forwarding was configured.
