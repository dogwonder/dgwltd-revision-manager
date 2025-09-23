const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'editor': './src/editor.js',
        'revision-editor': './src/revision-editor.js'
    }
};