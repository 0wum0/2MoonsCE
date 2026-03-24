/**
 * FBX → GLB Batch Converter (CommonJS)
 * Run: node convert.cjs
 */
'use strict';

// --- DOM polyfill BEFORE requiring three ---
const { JSDOM } = require('jsdom');
const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
global.window      = dom.window;
global.document    = dom.window.document;
global.self        = global;
global.URL         = dom.window.URL;
global.Blob        = dom.window.Blob;
global.FileReader  = dom.window.FileReader;
global.XMLHttpRequest = dom.window.XMLHttpRequest;
global.Image       = dom.window.Image;
global.ImageData   = dom.window.ImageData;
global.createImageBitmap = dom.window.createImageBitmap;
Object.defineProperty(global, 'navigator', {
  value: { userAgent: 'node' }, writable: true, configurable: true
});

const fs   = require('fs');
const path = require('path');

const THREE      = require('three');
const { FBXLoader }    = require('three/examples/jsm/loaders/FBXLoader.js');
const { GLTFExporter } = require('three/examples/jsm/exporters/GLTFExporter.js');

const inputDir  = __dirname;
const outputDir = path.join(__dirname, 'glb');

if (!fs.existsSync(outputDir)) fs.mkdirSync(outputDir);

const files = fs.readdirSync(inputDir).filter(f => path.extname(f).toLowerCase() === '.fbx');
console.log(`Found ${files.length} FBX files:`, files);

const loader   = new FBXLoader();
const exporter = new GLTFExporter();

(async () => {
  for (const file of files) {
    const inPath  = path.join(inputDir, file);
    const outName = path.basename(file, '.fbx') + '.glb';
    const outPath = path.join(outputDir, outName);

    process.stdout.write(`Converting: ${file} → ${outName} ... `);

    try {
      const buf = fs.readFileSync(inPath);
      const ab  = buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength);

      const object = loader.parse(ab, inputDir + '/');
      // FBX units are usually cm → scale to meters
      object.scale.setScalar(0.01);
      object.updateMatrixWorld(true);

      // Strip textures — we colour ships in-game via MeshStandardMaterial tint
      object.traverse(child => {
        if (!child.isMesh) return;
        const mats = Array.isArray(child.material) ? child.material : [child.material];
        child.material = mats.map(m => {
          const nm = new THREE.MeshStandardMaterial({
            color: m.color || new THREE.Color(0x888888),
            metalness: 0.4,
            roughness: 0.6,
          });
          return nm;
        });
        if (!Array.isArray(child.material)) child.material = child.material[0];
      });

      await new Promise((resolve, reject) => {
        exporter.parse(
          object,
          (result) => {
            fs.writeFileSync(outPath, Buffer.from(result));
            console.log(`✓  (${(result.byteLength / 1024).toFixed(1)} KB)`);
            resolve();
          },
          (err) => { console.error(`✗  ${err}`); reject(err); },
          { binary: true, embedImages: false }
        );
      });

    } catch (e) {
      console.error(`✗  ${e.message}`);
    }
  }
  console.log('\nDone.');
})();
