// @ts-check
// Note: type annotations allow type checking and IDEs autocompletion

// Main configuration
const workshopName = 'security-basics-lab';
const organizationName = "mongodb-developer";

// Main page config
const title = "MongoDB Security Basics";
const tagLine = "Learn to secure your MongoDB deployments";
const startButtonTitle = "Start Learning";
const favicon = "img/favicon.svg"

// Main Page Features
const featureList = [
  {
    title: 'Atlas & On-Premises',
    illustration: 'img/coding.png',
    description: `
        Learn security best practices for both MongoDB Atlas and self-hosted deployments.
    `,
  },
  {
    title: 'Hands-On Examples',
    illustration: 'img/highfive.png',
    description: `
        Practice with real-world security configurations and implementations.
    `,
  },
  {
    title: 'Best Practices',
    illustration: 'img/writing.png',
    description: `
        Follow industry standards and MongoDB security best practices.
    `,
  },
];

// UTM Parameters
const utmAdvocateName = `pavel.duchovny`;
const utmWorkshopName = 'security_basics'
const utmParams = `utm_campaign=devrel&utm_source=workshop&utm_medium=cta&utm_content=${utmWorkshopName}&utm_term=${utmAdvocateName}`;

// Footer links
const footerLinks = [
  {
    label: "MongoDB Documentation",
    href: `https://docs.mongodb.com/manual/security/?${utmParams}`,
  },
  {
    label: "MongoDB Forums",
    href: `https://www.mongodb.com/community/forums/?${utmParams}`,
  },
  {
    label: "Developer Center",
    href: `https://www.mongodb.com/developer/?${utmParams}`,
  },
  {
    label: "MongoDB University",
    href: `https://learn.mongodb.com/?${utmParams}`,
  },
  {
    href: `https://github.com/${organizationName}/?${workshopName}`,
    label: "GitHub Repository",
  },
  {
    label: `© ${new Date().getFullYear()} MongoDB, Inc.`,
    href: "#",
  },
];

///////////////////////////////////////////////////////////////////////////////
// Core Configuration                                                         //
///////////////////////////////////////////////////////////////////////////////

const { themes } = require("prism-react-renderer");
const lightCodeTheme = themes.github;
const darkCodeTheme = themes.dracula;

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: `${title}`,
  tagline: `${tagLine}`,
  url: `https://${organizationName}.github.io`,
  baseUrl: `/${workshopName}/`,
  projectName: `${workshopName}`,
  organizationName: `${organizationName}`,
  trailingSlash: false,
  onBrokenLinks: "warn",
  onBrokenMarkdownLinks: "warn",
  favicon: `${favicon}`,
  deploymentBranch: "gh-pages",
  staticDirectories: ["static"],

  i18n: {
    defaultLocale: "en",
    locales: ["en"],
  },

  customFields: {
    startButtonTitle: `${startButtonTitle}`,
    featureList: featureList,
    utmParams,
  },

  presets: [
    [
      "classic",
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          sidebarPath: require.resolve("./sidebars.js"),
          editUrl: `https://github.com/${organizationName}/${workshopName}/blob/main`,
        },
        theme: {
          customCss: require.resolve("./src/css/custom.css"),
        },
      }),
    ],
  ],

  plugins: [require.resolve("docusaurus-lunr-search")],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      docs: {
        sidebar: {
          autoCollapseCategories: true,
          hideable: true,
        },
      },
      navbar: {
        title: `${title}`,
        logo: {
          alt: "MongoDB Logo",
          src: "img/logo.svg",
          srcDark: "img/logo-dark.svg",
          className: "navbar-logo",
          width: "135px",
          height: "100%",
        },
        items: [
          {
            type: 'doc',
            docId: 'intro',
            position: 'left',
            label: 'Tutorial',
          },
          {
            href: `https://github.com/${organizationName}/${workshopName}`,
            label: 'GitHub',
            position: 'right',
          },
        ],
      },
      footer: {
        style: "dark",
        links: footerLinks,
      },
      prism: {
        theme: lightCodeTheme,
        darkTheme: darkCodeTheme,
        additionalLanguages: ["powershell", "yaml", "json"],
      },
    }),

  markdown: {
    mermaid: true,
  },
  themes: ["@docusaurus/theme-mermaid"],
};

module.exports = config;
