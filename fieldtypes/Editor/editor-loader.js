// Main editor loader - sets up EditorModes and HaruEditor
(function() {
  'use strict';
  
  // Create the main HaruEditor object
  window.HaruEditor = {
    version: '1.0.0',
    EditorModes: {},
    plugins: {
      blocks: []
    }
  };
  
  // Create EditorModes object
  window.EditorModes = {};
  
  // Advanced editor mode will be loaded by modes/advanced.js
  
  // Create EditorUI object
  window.EditorUI = {};
  
  // Create EditorCore.Bus mock
  window.EditorCore = {
    Bus: {
      on: function(callback) {
        // Simple mock - just return a function to unsubscribe
        return function() { /* Mock unsubscribe called */ };
      },
      emit: function(event) {
        /* Mock Bus.emit: ${event} */
      }
    }
  };
  
  /* Editor loader initialized */
})();
