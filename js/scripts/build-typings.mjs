import { execFileSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptsDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptsDir, '..');

const srcTypes = path.join(root, 'src', '@types');
const dist = path.join(root, 'dist-typings');
const distTypes = path.join(dist, '@types');

const tsc = path.join(root, 'node_modules', 'typescript', 'bin', 'tsc');

function processDeclarations(directory) {
  for (const entry of readdirSync(directory)) {
    const file = path.join(directory, entry);

    if (statSync(file).isDirectory()) {
      processDeclarations(file);
      continue;
    }

    if (!file.endsWith('.d.ts')) {
      continue;
    }

    const source = readFileSync(file, 'utf8');

    const output = source.replaceAll('../src/@types', '@types').replaceAll('..\\src\\@types', '@types');

    if (output !== source) {
      writeFileSync(file, output, 'utf8');
    }
  }
}

rmSync(dist, {
  recursive: true,
  force: true,
});

mkdirSync(dist, {
  recursive: true,
});

if (existsSync(srcTypes)) {
  cpSync(srcTypes, distTypes, {
    recursive: true,
  });
}

if (!existsSync(tsc)) {
  throw new Error('TypeScript was not found. Run npm install first.');
}

execFileSync(process.execPath, [tsc, '--project', path.join(root, 'tsconfig.json')], {
  cwd: root,
  stdio: 'inherit',
});

processDeclarations(dist);

console.log('Type declarations built successfully.');
