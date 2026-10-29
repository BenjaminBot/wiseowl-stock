import { build } from 'esbuild';
await build({
    entryPoints: ['resources/js/components/isbn-scanner.js', 'resources/js/components/book-lookup.js'],
    outdir: 'resources/js/dist',
    bundle: true,
    format: 'esm',
    platform: 'browser',
    target: ['safari16', 'chrome110'],
    minify: true,
    legalComments: 'inline',
});
