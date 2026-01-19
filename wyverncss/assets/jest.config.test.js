/**
 * Minimal Jest configuration for testing
 */
module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: [ '<rootDir>/src/setupTests.js' ],

  transform: {
    '^.+\\.[jt]sx?$': '@wordpress/scripts/config/babel-transform.js',
  },

  moduleNameMapper: {
    '\\.module\\.(css|less|scss|sass)$': 'identity-obj-proxy',
    '\\.(css|less|scss|sass)$': '<rootDir>/src/__mocks__/styleMock.js',
    '^@/(.*)$': '<rootDir>/src/$1',
  },

  testMatch: [
    '<rootDir>/src/**/__tests__/**/*.test.{ts,tsx}',
    '<rootDir>/src/**/*.test.{ts,tsx}',
  ],

  moduleFileExtensions: [ 'js', 'jsx', 'ts', 'tsx', 'json' ],
};
