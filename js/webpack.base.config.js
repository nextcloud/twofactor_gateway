const path = require('path');
const {VueLoaderPlugin} = require('vue-loader')

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
			{
				test: /\.vue$/,
				loader: 'vue-loader',
				options: {
					loaders: {}
				}
			},
			{
				test: /\.css$/,
				use: [
					{
						loader: 'vue-style-loader'
					},
					{
						loader: 'css-loader',
						options: {
							modules: true,
							localIdentName: '[local]_[hash:base64:8]'
						}
					}
				]
			}
		]
	},
	plugins: [
		new VueLoaderPlugin()
	]
};
