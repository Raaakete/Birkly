window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['text'] = {
  label: 'Text',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    return `<input type="text" class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" ${fieldConfig.required ? 'required' : ''} placeholder="${fieldConfig.placeholder || ''}" value="${value ? value.replace(/\"/g, '&quot;') : ''}">`;
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