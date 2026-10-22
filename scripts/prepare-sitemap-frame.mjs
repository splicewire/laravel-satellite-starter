// Prepare only Frame from an explicit fresh source clone, against this consumer's installed peers.
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import {
    existsSync,
    mkdirSync,
    readFileSync,
    readdirSync,
    realpathSync,
    symlinkSync,
    writeFileSync,
} from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import process from 'node:process';

const consumer = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const [sourceArg, toolsArg, expectedRef] = process.argv.slice(2);

if (!sourceArg || !toolsArg || !/^[a-f0-9]{40}$/.test(expectedRef ?? '')) {
    throw Error(
        'Usage: node scripts/prepare-sitemap-frame.mjs FRESH_JS_SOURCE NEW_TOOLS_DIR EXACT_REF',
    );
}

const source = realpathSync(sourceArg),
    tools = resolve(toolsArg);
const git = (...args) =>
    execFileSync('git', ['-C', source, ...args], { encoding: 'utf8' }).trim();

if (
    git('rev-parse', 'HEAD') !== expectedRef ||
    git('status', '--porcelain', '--', 'frame/src', 'frame/package.json')
) {
    throw Error('Use the exact unchanged supplied Frame source.');
}

if (existsSync(tools) || existsSync(join(source, 'node_modules'))) {
    throw Error(
        'Use new owned preparation directories, never an installed source checkout.',
    );
}

const manifest = JSON.parse(readFileSync(join(source, 'frame/package.json')));

if (manifest.name !== '@schemastud/frame') {
    throw Error('Expected the supplied Schemastud source root.');
}

const installedFrame = realpathSync(
    join(consumer, 'node_modules/@schemastud/frame'),
);
const requirePeer = createRequire(join(installedFrame, 'package.json'));
const lock = JSON.parse(
    readFileSync(join(source, 'package-lock.json')),
).packages;
const dependencies = {};

for (const name of ['tsup', 'typescript', '@types/react', '@types/react-dom']) {
    const pin =
        lock[`frame/node_modules/${name}`] ?? lock[`node_modules/${name}`];

    if (!pin?.version) {
        throw Error(`No source-locked build tool: ${name}`);
    }

    dependencies[name] = pin.version;
}

mkdirSync(tools);
writeFileSync(
    join(tools, 'package.json'),
    JSON.stringify({ private: true, dependencies }, null, 2) + '\n',
);
execFileSync('pnpm', ['install', '--ignore-scripts'], {
    cwd: tools,
    stdio: 'inherit',
});
const modules = join(source, 'node_modules');
mkdirSync(modules);

for (const name of readdirSync(join(tools, 'node_modules'))) {
    if (name !== '.pnpm') {
        symlinkSync(
            realpathSync(join(tools, 'node_modules', name)),
            join(modules, name),
        );
    }
}

const peers = [];

for (const [name, declared] of Object.entries({
    ...manifest.dependencies,
    ...manifest.peerDependencies,
})) {
    const path = requirePeer.resolve
        .paths(name)
        ?.map((parent) => join(parent, name))
        .find((path) => existsSync(join(path, 'package.json')));

    if (!path) {
        throw Error(`Missing installed consumer peer: ${name}`);
    }

    const target = realpathSync(path),
        metadata = readFileSync(join(target, 'package.json'));
    const destination = join(modules, name);
    mkdirSync(dirname(destination), { recursive: true });

    if (!existsSync(destination)) {
        symlinkSync(target, destination);
    }

    peers.push({
        name,
        declared,
        path: target,
        version: JSON.parse(metadata).version,
        metadataSha256: createHash('sha256').update(metadata).digest('hex'),
    });
}

execFileSync('npm', ['run', 'build', '--workspace', 'frame'], {
    cwd: source,
    stdio: 'inherit',
});
const outputs = {};

for (const name of readdirSync(join(source, 'frame/dist'))) {
    outputs[name] = createHash('sha256')
        .update(readFileSync(join(source, 'frame/dist', name)))
        .digest('hex');
}

writeFileSync(
    join(tools, 'receipt.json'),
    JSON.stringify(
        { source, expectedRef, dependencies, peers, outputs },
        null,
        2,
    ) + '\n',
);
