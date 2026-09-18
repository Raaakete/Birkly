window.HaruFieldtypes = window.HaruFieldtypes || {};

window.HaruFieldtypes.password = {
  label: 'Password',
  category: 'misc',
  render: function (fieldConfig) {
    var min = fieldConfig.min_length ? ' minlength="' + fieldConfig.min_length + '"' : '';
    var req = fieldConfig.required ? ' required' : '';
    return (
      '<input type="password" class="input" name="' +
      fieldConfig.name +
      '" id="field_' +
      fieldConfig.name +
      '" autocomplete="new-password" placeholder="Enter password"' +
      min +
      req +
      ' value="">'
    );
  },
  getValue: function (fieldName) {
    var input = document.getElementById('field_' + fieldName);
    return input ? input.value : '';
  },
  setValue: function () {
    /* Never hydrate plaintext passwords in admin UI */
  },
};
