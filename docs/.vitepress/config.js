import { defineConfig } from 'vitepress'

const description = 'PSR-14 event dispatcher for PHP with listener priorities, subscribers and type-hierarchy matching.'

export default defineConfig({
  title: 'Solo Event Dispatcher',
  description,
  base: '/Event-Dispatcher/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/Event-Dispatcher/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#f59e0b' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Solo Event Dispatcher' }],
    ['meta', { property: 'og:description', content: description }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/' },
      { text: 'API', link: '/guide/api' },
      { text: 'Details', link: '/guide/details' },
      { text: 'v2.0.0', link: 'https://github.com/SoloPHP/Event-Dispatcher/releases' },
    ],

    sidebar: [
      {
        text: 'Solo Event Dispatcher · 2.0',
        items: [
          { text: 'Getting started', link: '/guide/' },
          { text: 'API reference', link: '/guide/api' },
          { text: 'Behavior in detail', link: '/guide/details' },
          { text: 'Upgrading from 1.x', link: 'https://github.com/SoloPHP/Event-Dispatcher/blob/main/UPGRADE.md' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/SoloPHP/Event-Dispatcher' },
    ],

    footer: {
      message: 'MIT licensed.',
      copyright: 'Copyright © 2025-2026 SoloPHP',
    },

    editLink: {
      pattern: 'https://github.com/SoloPHP/Event-Dispatcher/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    search: {
      provider: 'local',
    },
  },
})
