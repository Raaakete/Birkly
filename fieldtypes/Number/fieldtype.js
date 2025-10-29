window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['number'] = {
  label: 'Number',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    return `<input type="number" class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" ${fieldConfig.required ? 'required' : ''} placeholder="${fieldConfig.placeholder || ''}" value="${value ? value : ''}">`;
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