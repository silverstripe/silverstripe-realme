const Path = require('path');
const webpackConfig = require('@silverstripe/webpack-config');
const {
  moduleCSS,
  pluginCSS,
} = webpackConfig;

const ENV = process.env.NODE_ENV;
const PATHS = {
  MODULES: 'node_modules',
  FILES_PATH: '../',
  ROOT: Path.resolve(),
  SRC: Path.resolve('client/src'),
  DIST: Path.resolve('client'),
};
const CI_PATHS = {
    ...PATHS,
    DIST: Path.resolve('client/dist'),
}

const plugins = pluginCSS(ENV, PATHS);
for (const plugin of plugins) {
    if (plugin.filename === 'styles/[name].css') {
        plugin.filename = 'css/[name].css';
    }
}

const config = [
  {
    name: 'css',
    entry: {
        realme: `${PATHS.SRC}/styles/bundle.scss`,
    },
    output: {
      path: PATHS.DIST,
      filename: 'css/[name].css',
    },
    devtool: (ENV !== 'production') ? 'source-map' : '',
    module: moduleCSS(ENV, PATHS),
    plugins
  },
  // We need a separate run for CI, because our CI expects output to be in client/dist but
  // for backwards-compatability we need to keep the old folder structure for the runtime output.
  {
    name: 'css-for-ci',
    entry: {
        realme: `${CI_PATHS.SRC}/styles/bundle.scss`,
    },
    output: {
      path: CI_PATHS.DIST,
      filename: 'css/[name].css',
    },
    devtool: (ENV !== 'production') ? 'source-map' : '',
    module: moduleCSS(ENV, CI_PATHS),
    plugins
  }
];

module.exports = config;
