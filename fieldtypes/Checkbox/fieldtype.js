window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['checkbox'] = {
  label: 'Checkbox',
  category: 'input',
  dynamicItemsEligible: false,
  render: function(fieldConfig, value) {
    return `<input type="checkbox" name="${fieldConfig.name}" id="field_${fieldConfig.name}" value="1"${value ? ' checked' : ''}>`;
  },
  getValue: function(fieldName) {
    var input = document.getElementById('field_' + fieldName);
    return input ? input.checked : false;
  },
  setValue: function(fieldName, value) {
    var input = document.getElementById('field_' + fieldName);
    if (input) input.checked = !!value;
  },
  renderConfig: function(config, onChange) {
    return null;
  }
}; 