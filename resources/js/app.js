// Resources: markdown preview helper only.
// Livewire v3+ bundles its own Alpine.js and starts it via window.Alpine —
// importing Alpine separately AND calling Alpine.start() conflicts with
// Livewire's bundled instance and breaks wire: handlers.
import './markdown-preview.js';