const { getDefaultConfig } = require("expo/metro-config");
const { withUniwindConfig } = require("uniwind/metro");

const config = getDefaultConfig(__dirname);

// The generated native projects are never bundled. Watching them made
// Metro run out of memory while a release build wrote thousands of files
// into android/build.
const nativeFolders = /[\\/](android|ios)[\\/].*/;
config.resolver.blockList = [
  ...(Array.isArray(config.resolver.blockList)
    ? config.resolver.blockList
    : config.resolver.blockList
      ? [config.resolver.blockList]
      : []),
  new RegExp(`^${__dirname.replace(/[\\/]/g, "[\\\\/]").replace(/[.*+?^${}()|]/g, "\\$&")}${nativeFolders.source}`),
];

module.exports = withUniwindConfig(config, {
  cssEntryFile: "./src/global.css",
});
