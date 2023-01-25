const Path = require('path');
const { JavascriptWebpackConfig, CssWebpackConfig } = require('@silverstripe/webpack-config');
const CopyWebpackPlugin = require('copy-webpack-plugin');

const PATHS = {
  ROOT: Path.resolve(),
  SRC: Path.resolve('client/src'),
  DIST: Path.resolve('client/dist'),
};

const config = [
  // Main JS bundle
  new JavascriptWebpackConfig('js', PATHS, 'silverstripe/realme')
    .setEntry({
      realme: `${PATHS.SRC}/js/realme.js`,
    })
    .mergeConfig({
      plugins: [
        new CopyWebpackPlugin({
          patterns: [
            {
              // needed for templates
              from: `${PATHS.SRC}/images/RealMe-logo@2x.png`,
              to: `${PATHS.DIST}/images`
            },
          ]
        })
      ]
    })
    .getConfig(),
  // sass to css
  new CssWebpackConfig('css', PATHS)
    .setEntry({
      realme: `${PATHS.SRC}/styles/bundle.scss`,
    })
    .getConfig(),
];

module.exports = config;
