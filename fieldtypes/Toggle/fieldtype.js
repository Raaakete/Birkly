window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['toggle'] = {
  label: 'Toggle',
  category: 'input',
  dynamicItemsEligible: false,
  render: function(fieldConfig, value) {
    return `<div class="toggle-field-wrapper"><label class="switch" for="field_${fieldConfig.name}"><input type="checkbox" name="${fieldConfig.name}" id="field_${fieldConfig.name}" value="1"${value ? ' checked' : ''}><span class="switch__slider"></span></label></div>`;
  },
  getValue: function(fieldName) {
    var input = document.getElementById('field_' + fieldName);
    return input ? !!input.checked : false;
  },
  setValue: function(fieldName, value) {
    var input = document.getElementById('field_' + fieldName);
    if (input) input.checked = !!value;
  },
  renderConfig: function(config, onChange) {
    return null;
  }
};
