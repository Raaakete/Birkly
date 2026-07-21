window.HaruFieldtypes = window.HaruFieldtypes || {};
window.HaruFieldtypes['phone'] = {
  label: 'Phone',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    return `<input type="tel" class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" ${fieldConfig.required ? 'required' : ''} inputmode="tel" placeholder="${fieldConfig.placeholder || '+1 555 123 4567'}" value="${value ? String(value).replace(/"/g, '&quot;') : ''}">`;
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
