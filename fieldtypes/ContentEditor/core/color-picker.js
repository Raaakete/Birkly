// Color picker utilities for ContentEditor
window.ContentEditorColorPicker = {
  /**
   * Draw color wheel on canvas
   */
  drawColorWheel(canvas, brightness = 0.5) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = Math.min(centerX, centerY) - 2;
    const innerRadius = radius * 0.3;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Draw color wheel using image data for better performance
    const imageData = ctx.createImageData(canvas.width, canvas.height);
    const data = imageData.data;
    
    for (let y = 0; y < canvas.height; y++) {
      for (let x = 0; x < canvas.width; x++) {
        const dx = x - centerX;
        const dy = y - centerY;
        const distance = Math.sqrt(dx * dx + dy * dy);
        const angle = (Math.atan2(dy, dx) * 180 / Math.PI + 360) % 360;
        const index = (y * canvas.width + x) * 4;
        
        if (distance <= radius && distance > innerRadius) {
          // Color wheel ring
          const saturation = Math.min(1, (distance - innerRadius) / (radius - innerRadius));
          const rgb = this.hslToRgb(angle / 360, saturation, brightness);
          data[index] = rgb[0];     // R
          data[index + 1] = rgb[1]; // G
          data[index + 2] = rgb[2]; // B
          data[index + 3] = 255;    // A
        } else if (distance <= innerRadius) {
          // Inner circle - white/gray based on brightness
          const gray = Math.round(255 * brightness);
          data[index] = gray;
          data[index + 1] = gray;
          data[index + 2] = gray;
          data[index + 3] = 255;
        } else {
          // Outside - transparent
          data[index] = 0;
          data[index + 1] = 0;
          data[index + 2] = 0;
          data[index + 3] = 0;
        }
      }
    }
    
    ctx.putImageData(imageData, 0, 0);
  },
  
  /**
   * Draw brightness slider
   */
  drawBrightnessSlider(canvas, hue, saturation) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const width = canvas.width;
    const height = canvas.height;
    
    ctx.clearRect(0, 0, width, height);
    
    // Draw gradient from black to full color to white
    const gradient = ctx.createLinearGradient(0, 0, width, 0);
    gradient.addColorStop(0, '#000000');
    
    // Calculate the full color at 50% brightness
    const rgb = this.hslToRgb(hue, saturation, 0.5);
    gradient.addColorStop(0.5, `rgb(${rgb[0]}, ${rgb[1]}, ${rgb[2]})`);
    gradient.addColorStop(1, '#FFFFFF');
    
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, width, height);
    
    // Draw border
    ctx.strokeStyle = '#d1d5db';
    ctx.lineWidth = 1;
    ctx.strokeRect(0, 0, width, height);
  },
  
  /**
   * Convert HSL to RGB
   */
  hslToRgb(h, s, l) {
    let r, g, b;
    
    if (s === 0) {
      r = g = b = l; // achromatic
    } else {
      const hue2rgb = (p, q, t) => {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1/6) return p + (q - p) * 6 * t;
        if (t < 1/2) return q;
        if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
        return p;
      };
      
      const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
      const p = 2 * l - q;
      r = hue2rgb(p, q, h + 1/3);
      g = hue2rgb(p, q, h);
      b = hue2rgb(p, q, h - 1/3);
    }
    
    return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
  },
  
  /**
   * Convert RGB to HSL
   */
  rgbToHsl(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;
    
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;
    
    if (max === min) {
      h = s = 0; // achromatic
    } else {
      const d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      
      switch (max) {
        case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
        case g: h = ((b - r) / d + 2) / 6; break;
        case b: h = ((r - g) / d + 4) / 6; break;
      }
    }
    
    return [h * 360, s, l];
  },
  
  /**
   * Convert hex to RGB
   */
  hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? [
      parseInt(result[1], 16),
      parseInt(result[2], 16),
      parseInt(result[3], 16)
    ] : null;
  },
  
  /**
   * Convert RGB to hex
   */
  rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(x => {
      const hex = x.toString(16);
      return hex.length === 1 ? '0' + hex : hex;
    }).join('').toUpperCase();
  },
  
  /**
   * Get color from wheel position
   */
  getColorFromWheel(canvas, x, y) {
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const dx = x - centerX;
    const dy = y - centerY;
    const distance = Math.sqrt(dx * dx + dy * dy);
    const radius = Math.min(centerX, centerY) - 2;
    
    if (distance > radius) return null;
    
    const angle = (Math.atan2(dy, dx) * 180 / Math.PI + 360) % 360;
    
    // Calculate saturation based on distance (same logic as drawing)
    let saturation = 0;
    if (distance > radius * 0.3) {
      saturation = Math.min(1, (distance - radius * 0.3) / (radius * 0.7));
    }
    
    const brightness = 0.5; // Will be adjusted by brightness slider
    
    return { hue: angle, saturation, brightness };
  },
  
  /**
   * Get brightness from slider position
   */
  getBrightnessFromSlider(canvas, x) {
    return Math.max(0, Math.min(1, x / canvas.width));
  }
};

