// Simple pub/sub bus for editor commands
window.EditorCore = window.EditorCore || {};
(function(){
  const listeners = new Set();
  function emit(intent){ listeners.forEach(fn => { try{ fn(intent); }catch(e){} }); }
  function on(handler){ listeners.add(handler); return () => listeners.delete(handler); }
  window.EditorCore.Bus = { emit, on };
})();


