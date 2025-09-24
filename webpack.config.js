// webpack.config.js
const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry,
		index: path.resolve(process.cwd(), 'src', 'index.js'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(process.cwd(), 'build'),
		filename: '[name].js', // so this becomes build/index.js
	},
	entry: {
		...defaultConfig.entry,
		index: path.resolve(process.cwd(), 'src', 'index.js'),
		'editor-sidebar': path.resolve(process.cwd(), 'src', 'editor-sidebar', 'index.js'),
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...defaultConfig.resolve.alias,
			'@wpfn/styles': path.resolve(process.cwd(), 'src/utils/styles'),
			'@wpfn/components': path.resolve(process.cwd(), 'src/components'),
		},
	},
};
