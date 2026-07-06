// @ts-check
import {themes as prismThemes} from 'prism-react-renderer';

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'phpx-server',
  tagline: 'React Server Components ideas — ported to PHP',
  favicon: 'img/favicon.ico',

  url: 'https://attitude.github.io',
  baseUrl: '/phpx-server/',

  organizationName: 'attitude',
  projectName: 'phpx-server',

  onBrokenLinks: 'throw',

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          sidebarPath: './sidebars.js',
          editUrl: 'https://github.com/attitude/phpx-server/edit/main/docs/',
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      colorMode: {
        defaultMode: 'light',
        respectPrefersColorScheme: true,
      },
      navbar: {
        title: 'phpx-server',
        items: [
          {
            type: 'docSidebar',
            sidebarId: 'docsSidebar',
            position: 'left',
            label: 'Docs',
          },
          {
            href: 'https://github.com/attitude/phpx-server',
            label: 'GitHub',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Docs',
            items: [
              {label: 'Introduction', to: '/docs/introduction'},
              {label: 'Getting started', to: '/docs/getting-started'},
            ],
          },
          {
            title: 'More',
            items: [
              {label: 'GitHub', href: 'https://github.com/attitude/phpx-server'},
              {label: 'PHPX', href: 'https://github.com/attitude/phpx'},
            ],
          },
        ],
        copyright: `Copyright © ${new Date().getFullYear()} Martin Adamko. Built on PHPX.`,
      },
      prism: {
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
        additionalLanguages: ['php', 'bash', 'jsx', 'tsx', 'json'],
      },
    }),
};

export default config;
