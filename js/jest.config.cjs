module.exports = require('@flarum/jest-config')({
  extensionsToTreatAsEsm: [],
  setupFiles: [],
  setupFilesAfterEnv: [],
  modulePaths: ['<rootDir>/node_modules'],
  testMatch: ['<rootDir>/tests/**/*.test.cjs'],
  moduleNameMapper: {
    '^flarum/(common|forum)/app$': '<rootDir>/tests/app.cjs',
    '^flarum/(.*)$': '<rootDir>/../vendor/flarum/core/js/src/$1',
  },
});
