/**
 * FBX → GLB Batch Converter
 * Uses three.js FBXLoader + GLTFExporter in Node.js
 * Run: node convert.mjs
 */

import { createRequire } from 'module';
import { readFileSync, writeFileSync, readdirSync } from 'fs';
import { join, dirname, basename, extname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// We use the three/examples FBXLoader which requires a DOM-like env
// Polyfill minimal globals needed by Three.js in Node
import { JSDOM } from 'jsdom';
const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
global.window   = dom.window;
global.document = dom.window.document;
try { global.navigator = { userAgent: 'node' }; } catch(e) {
  Object.defineProperty(global, 'navigator', { value: { userAgent: 'node' }, writable: true, configurable: true });
}
global.self = global;
global.URL = dom.window.URL;
global.Blob = dom.window.Blob;

import * as THREE from 'three';
import { FBXLoader } from 'three/examples/jsm/loaders/FBXLoader.js';
import { GLTFExporter } from 'three/examples/jsm/exporters/GLTFExporter.js';

const inputDir  = __dirname;
const outputDir = join(__dirname, 'glb');

// Create output dir
import { mkdirSync, existsSync } from 'fs';
if (!existsSync(outputDir)) mkdirSync(outputDir);

const files = readdirSync(inputDir).filter(f => extname(f).toLowerCase() === '.fbx');
console.log(`Found ${files.length} FBX files:`, files);

const loader   = new FBXLoader();
const exporter = new GLTFExporter();

for (const file of files) {
    const inPath  = join(inputDir, file);
    const outName = basename(file, '.fbx') + '.glb';
    const outPath = join(outputDir, outName);

    console.log(`Converting: ${file} → ${outName}`);

    try {
        const buffer = readFileSync(inPath);
        const ab     = buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + buffer.byteLength);

        const object = loader.parse(ab, inputDir + '/');

        // Normalize scale (FBX units are often cm → convert to meters)
        object.scale.setScalar(0.01);
        object.updateMatrixWorld(true);

        await new Promise((resolve, reject) => {
            exporter.parse(
                object,
                (result) => {
                    writeFileSync(outPath, Buffer.from(result));
                    console.log(`  ✓ Saved ${outPath} (${(result.byteLength / 1024).toFixed(1)} KB)`);
                    resolve();
                },
                (err) => { console.error(`  ✗ Export failed: ${err}`); reject(err); },
                { binary: true, embedImages: true }
            );
        });

    } catch (e) {
        console.error(`  ✗ Failed: ${file} — ${e.message}`);
    }
}

console.log('\nDone.');
