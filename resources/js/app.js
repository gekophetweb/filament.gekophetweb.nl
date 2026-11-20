import { initSiteNav } from './site-nav';
import { stlEstimate } from './stl-estimate';

// Alpine (bundled with Livewire) starts after this module ran, so x-data can use it.
window.stlEstimate = stlEstimate;

document.querySelectorAll('.site-header').forEach(initSiteNav);
