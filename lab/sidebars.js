/** @type {import('@docusaurus/plugin-content-docs').SidebarsConfig} */
const sidebars = {
  tutorialSidebar: [
    {
      type: 'doc',
      id: 'intro',
      label: 'Introduction',
    },
    { 
      type: 'category',
      label : "Initial Setup and network",
      items : [
        {
          type: 'doc',
          id: 'setup/index',
          label: '👐 Run: Initial Setup',
        },
        {
          type: 'doc',
          id: 'atlas/index',
          label: 'MongoDB Network Security',
        },
        {
          type: 'doc',
          id: 'challenge/network',
          label : '👐 Run: Setup Network Access'
        }]
    },
    { 
      type: 'category',
      label : "Authentication Task",
      items : [ {
      type: 'doc',
      id: 'authentication/index',
      label: 'Authentication',
    },
    {
      type: 'doc',
      id: 'challenge/authentication',
      label: '👐 Run: Setup Authentication',
    }
   ]},
    {
      type: 'category',
      label: 'RBAC Task',
      items: [
        {
          type: 'doc',
          id: 'rbac/index',
          label: 'Role-Based Access Control',
        },
        {
          type: 'doc',
          id: 'challenge/rbac',
          label: '👐 Run: Setup RBAC',
        },
      ],
    },
    {
      type: 'category',
      label: 'Queryable Encryption Task',
      items: [
        {
          type: 'doc',
          id: 'queryable-encryption/index',
          label: 'Queryable Encryption',
        },
        {
          type: 'doc',
          id: 'challenge/queryable-encryption',
          label: '👐 Run: Setup Queryable Encryption',
        },
      ],
    },
    {
      type: 'doc',
      id: 'considerations/index',
      label: 'Security Considerations and Summary',
    }
  ],
};

module.exports = sidebars;
