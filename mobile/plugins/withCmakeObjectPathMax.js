const { withGradleProperties } = require('expo/config-plugins');

const PROPERTY = 'expo.android.cmakeObjectPathMax';
const VALUE = '250';

/** Keep generated C++ object paths under the Windows 260-character limit. */
function withCmakeObjectPathMax(config) {
  return withGradleProperties(config, (config) => {
    const items = config.modResults;
    const existing = items.find((item) => item.type === 'property' && item.key === PROPERTY);

    if (existing) {
      existing.value = VALUE;
      return config;
    }

    items.push({ type: 'property', key: PROPERTY, value: VALUE });
    return config;
  });
}

module.exports = withCmakeObjectPathMax;
