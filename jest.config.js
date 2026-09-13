module.exports = {
  testMatch: ['**/tests/js/**/*.test.js'],
  testEnvironment: 'jsdom',
  collectCoverage: false,
  reporters: ['default', 'jest-junit'],
};
