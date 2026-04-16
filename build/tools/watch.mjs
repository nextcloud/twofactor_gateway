import { spawn } from 'node:child_process'
import { createInterface } from 'node:readline'

const ignoredSingleLineWarnings = [
	'build.outDir must not be the same directory of root or a parent directory of root',
	'../../tsconfig.json:2:12:',
	'2 │   "extends": "@vue/tsconfig/tsconfig.json",',
	'╵              ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~',
]

let skippingTsconfigWarning = false

function shouldIgnoreLine(line) {
	if (line.includes('Cannot find base config file "@vue/tsconfig/tsconfig.json"')) {
		skippingTsconfigWarning = true
		return true
	}

	if (skippingTsconfigWarning) {
		if (line.trim() === '') {
			skippingTsconfigWarning = false
		}
		return true
	}

	return ignoredSingleLineWarnings.some((warning) => line.includes(warning))
}

function pipeFiltered(stream, target) {
	const reader = createInterface({ input: stream })
	reader.on('line', (line) => {
		if (!shouldIgnoreLine(line)) {
			target.write(`${line}\n`)
		}
	})
	return reader
}

const child = spawn(
	process.execPath,
	[
		'./node_modules/vite/bin/vite.js',
		'build',
		'--mode',
		'development',
		'--watch',
		'--config',
		'vite.config.ts',
		'--clearScreen',
		'false',
	],
	{
		cwd: process.cwd(),
		env: process.env,
		stdio: ['inherit', 'pipe', 'pipe'],
	},
)

pipeFiltered(child.stdout, process.stdout)
pipeFiltered(child.stderr, process.stderr)

for (const signal of ['SIGINT', 'SIGTERM']) {
	process.on(signal, () => {
		child.kill(signal)
	})
}

child.on('exit', (code, signal) => {
	if (signal) {
		process.kill(process.pid, signal)
		return
	}
	process.exit(code ?? 0)
})