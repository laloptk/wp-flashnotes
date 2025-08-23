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
};
