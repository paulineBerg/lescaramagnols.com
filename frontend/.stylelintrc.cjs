module.exports = {
  extends: ['stylelint-config-standard', 'stylelint-config-standard-scss'],
  ignoreFiles: ['dist/**/*', 'node_modules/**/*'],
  rules: {
    'at-rule-no-unknown': null,
    'scss/at-rule-no-unknown': true,
    'no-descending-specificity': null,
    'selector-class-pattern': '^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*(?:__(?:[a-z0-9]+(?:[-_][a-z0-9]+)*))?(?:--(?:[a-z0-9]+(?:[-_][a-z0-9]+)*))*$'
  }
};
