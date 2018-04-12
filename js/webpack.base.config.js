const path = require('path');

module.exports = {
	entry: './js/init.js',
	node: {
		fs: 'empty'
	},
	output: {
		filename: 'build.js',
		path: path.resolve(__dirname, 'build')
	},
	resolve: {
		modules: [path.resolve(__dirname), 'node_modules'],
	},
	module: {
		rules: [
		]
	}
};
