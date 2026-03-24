/**
 * FBX → GLB Batch Converter v2
 * Uses three-stdlib FBXLoader + GLTFExporter with canvas polyfill
 */
'use strict';

const { createCanvas, Image } = require('canvas');
const { JSDOM } = require('jsdom');

// Full DOM + canvas polyfill before loading three
const dom = new JSDOM('<!DOCTYPE html>');
global.window   = dom.window;
global.document = dom.window.document;
global.self     = global;
global.Blob     = dom.window.Blob;
global.URL      = dom.window.URL;
global.FileReader = dom.window.FileReader;
global.XMLHttpRequest = dom.window.XMLHttpRequest;
Object.defineProperty(global, 'navigator', { value: { userAgent: 'node' }, configurable: true, writable: true });

// Canvas-backed HTMLCanvasElement for Three.js texture operations
global.HTMLCanvasElement = dom.window.HTMLCanvasElement;

// Patch document.createElement to return real canvas for 'canvas'
const origCreate = dom.window.document.createElement.bind(dom.window.document);
dom.window.document.createElement = function(tag, ...args) {
  if (tag === 'canvas') {
    const c = createCanvas(1, 1);
    c.style = {};
    return c;
  }
  return origCreate(tag, ...args);
};
global.document.createElement = dom.window.document.createElement;

// Image polyfill
global.Image = Image;
dom.window.Image = Image;

const fs   = require('fs');
const path = require('path');

const THREE = require('three');
const { FBXLoader }    = require('three-stdlib').FBXLoader ? require('three-stdlib') : require('three/examples/jsm/loaders/FBXLoader.js');

// Try three-stdlib first, fall back
let FBXLoaderClass, GLTFExporterClass;
try {
  const stdlib = require('three-stdlib');
  FBXLoaderClass    = stdlib.FBXLoader;
  GLTFExporterClass = stdlib.GLTFExporter;
  console.log('Using three-stdlib');
} catch(e) {
  FBXLoaderClass    = require('three/examples/jsm/loaders/FBXLoader.js').FBXLoader;
  GLTFExporterClass = require('three/examples/jsm/exporters/GLTFExporter.js').GLTFExporter;
  console.log('Using three/examples');
}

const inputDir  = __dirname;
const outputDir = path.join(__dirname, 'glb');
if (!fs.existsSync(outputDir)) fs.mkdirSync(outputDir, { recursive: true });

const files = fs.readdirSync(inputDir).filter(f => path.extname(f).toLowerCase() === '.fbx');
console.log(`Found ${files.length} FBX files`);

const loader   = new FBXLoaderClass();
const exporter = new GLTFExporterClass();

(async () => {
  for (const file of files) {
    const inPath  = path.join(inputDir, file);
    const outName = path.basename(file, '.fbx') + '.glb';
    const outPath = path.join(outputDir, outName);

    process.stdout.write(`  ${file} → ${outName} ... `);

    try {
      const buf = fs.readFileSync(inPath);
      const ab  = buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength);

      const object = loader.parse(ab, '');

      // Scale from cm to meters, reset rotation
      object.scale.setScalar(0.01);
      object.rotation.set(0, 0, 0);
      object.updateMatrixWorld(true);

      // Replace all materials with texture-free MeshStandardMaterial
      object.traverse(child => {
        if (!child.isMesh) return;
        const srcMats = Array.isArray(child.material) ? child.material : [child.material];
        const newMats = srcMats.map(m => new THREE.MeshStandardMaterial({
          color: (m && m.color) ? m.color.clone() : new THREE.Color(0x999999),
          metalness: 0.5,
          roughness: 0.5,
        }));
        child.material = newMats.length === 1 ? newMats[0] : newMats;
      });

      const glbBuffer = await new Promise((resolve, reject) => {
        exporter.parse(
          object,
          result => resolve(result),
          err    => reject(new Error(err)),
          { binary: true, embedImages: false }
        );
      });

      fs.writeFileSync(outPath, Buffer.from(glbBuffer));
      const kb = (glbBuffer.byteLength / 1024).toFixed(1);
      console.log(`✓ ${kb} KB`);

    } catch(e) {
      console.log(`✗ ${e.message}`);
    }
  }
  console.log('\nFinished.');
})();
