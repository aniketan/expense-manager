import './secure-context-polyfill';
import React from 'react';
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import '../css/app.css';

createInertiaApp({
  // Pages are loaded on demand so each one ships as its own chunk.
  resolve: async name => {
    const pages = import.meta.glob('./Pages/**/*.jsx')
    const page = await pages[`./Pages/${name}.jsx`]()

    // Pages handle their own layout
    if (page.default.layout === undefined) {
      page.default.layout = (page) => page
    }

    return page
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
})