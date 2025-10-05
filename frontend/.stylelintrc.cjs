module.exports = {
  extends: [
    'stylelint-config-standard',
    'stylelint-config-standard-scss'
  ],
  ignoreFiles: ['dist/**/*', 'node_modules/**/*'],
  rules: {
    'at-rule-no-unknown': null,
    'scss/at-rule-no-unknown': true
  }
};
