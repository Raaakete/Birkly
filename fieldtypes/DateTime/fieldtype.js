window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['datetime'] = {
  label: 'DateTime',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    return `<input type="datetime-local" class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" value="${value || ''}" ${fieldConfig.required ? 'required' : ''}>`;
  },
  getValue: function(fieldName) {
    var input = document.getElementById('field_' + fieldName);
    return input ? input.value : '';
  },
  setValue: function(fieldName, value) {
    var input = document.getElementById('field_' + fieldName);
    if (input) input.value = value || '';
  },
  renderConfig: function(config, onChange) {
    return null;
  }
}; 