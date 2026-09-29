window.HaruFieldtypes = window.HaruFieldtypes || {};

function normalizeSelectOptions(options) {
  return (options || []).map(function(opt) {
    if (typeof opt === 'string') {
      var trimmed = opt.trim();
      if (!trimmed) return null;
      return {
        label: trimmed,
        value: trimmed.toLowerCase().replace(/[^a-z0-9]+/g, '_')
      };
    }
    if (!opt || typeof opt !== 'object') return null;
    var value = opt.value != null ? String(opt.value) : (opt.label != null ? String(opt.label) : '');
    var label = opt.label != null ? String(opt.label) : value;
    if (!value && !label) return null;
    return { label: label || value, value: value || label };
  }).filter(Boolean);
}

window.HaruFieldtypes['select'] = {
  label: 'Select',
  category: 'input',
  dynamicItemsEligible: false,
  render: function(fieldConfig, value) {
    let options = `<option value="">Select ${fieldConfig.label || fieldConfig.name}</option>`;
    normalizeSelectOptions(fieldConfig.options).forEach(opt => {
      options += `<option value="${opt.value}"${value == opt.value ? ' selected' : ''}>${opt.label}</option>`;
    });
    return `<select class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" ${fieldConfig.required ? 'required' : ''}>${options}</select>`;
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
    // config: {options: [{label, value}, ...]}
    let container = document.createElement('div');
    container.className = 'select-options-editor mt-1';
    container.innerHTML = `
      <label class="mb-1" style="font-size:0.95em;">Options:</label>
      <div class="options-list mb-1"></div>
      <button type="button" class="button is-small">Add Option</button>
    `;
    const optionsList = container.querySelector('.options-list');
    function renderOptions() {
      optionsList.innerHTML = '';
      (config.options || []).forEach((opt, j) => {
        const row = document.createElement('div');
        row.className = 'flex gap-1 mb-1 align-center';
        row.innerHTML = `
          <input type="text" class="input" placeholder="Label" value="${opt.label||''}" style="max-width:100px;">
          <input type="text" class="input" placeholder="Value" value="${opt.value||''}" style="max-width:100px;">
          <button type="button" class="button is-danger is-small">&times;</button>
        `;
        const [labelInput, valueInput, removeBtn] = row.children;
        labelInput.addEventListener('input', e => {
          config.options[j].label = e.target.value;
          onChange && onChange(config);
        });
        valueInput.addEventListener('input', e => {
          config.options[j].value = e.target.value;
          onChange && onChange(config);
        });
        removeBtn.addEventListener('click', () => {
          config.options.splice(j, 1);
          renderOptions();
          onChange && onChange(config);
        });
        optionsList.appendChild(row);
      });
    }
    renderOptions();
    container.querySelector('button.add-option-btn').addEventListener('click', () => {
      config.options = config.options || [];
      config.options.push({label:'',value:''});
      renderOptions();
      onChange && onChange(config);
    });
    return container;
  }
}; 