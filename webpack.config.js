const path = require('path');

module.exports = {
    entry: {
      bundle: '/assets/src/index.js',
    },    
    output: {
        path: path.resolve( __dirname, 'assets/dist' ),
        filename: '[name].js',
        clean: true
    }
}