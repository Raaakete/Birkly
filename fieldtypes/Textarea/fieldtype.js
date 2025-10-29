window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['textarea'] = {
  label: 'Textarea',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    return `<textarea class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" ${fieldConfig.required ? 'required' : ''} placeholder="${fieldConfig.placeholder || ''}">${value ? value : ''}</textarea>`;
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