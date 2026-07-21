window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['toggle'] = {
  label: 'Toggle',
  category: 'input',
  dynamicItemsEligible: false,
  render: function(fieldConfig, value) {
    return `<label class="toggle-switch"><input type="checkbox" name="${fieldConfig.name}" id="field_${fieldConfig.name}" value="1"${value ? ' checked' : ''} class="toggle-switch-input"><span class="toggle-switch-slider"></span></label>`;
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
