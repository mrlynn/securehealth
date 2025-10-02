[![.github/workflows/deploy.yml](https://github.com/mongodb-developer/security-basics-lab/actions/workflows/deploy.yml/badge.svg?branch=main)](https://github.com/mongodb-developer/security-basics-lab/actions/workflows/deploy.yml)

# Security Basics Lab

A MongoDB Security Basics Labs. Access it [here](https://mongodb-developer.github.io/security-basics-lab/)

## Workshop Logic

This workshop guides you through the following security concepts:

1.  **Initial Setup and Network Security**: Learn how to set up your environment and configure network access to your MongoDB Atlas cluster.
2.  **Authentication**: Explore different authentication methods, including username/password and X.509 certificate authentication.
3.  **Role-Based Access Control (RBAC)**: Implement fine-grained access control using RBAC to manage user permissions.
4.  **Queryable Encryption**: Discover how to encrypt data at rest and in transit while still being able to query it.

Each section includes a challenge where you can apply what you've learned. Follow the instructions in each challenge to complete the tasks.

## Contributing

As `main` is protected, submit a pull request to be reviewed.

### Local installation

1. Clone this repo
2. Install packages
```
npm i
```
3. Launch Lab
```
npm start
```

As you don't want to get the changes of these sample pages, just delete the `.git` folder and then `git init`.

Open `docusaurus.config.js` and change at least the `workshopName`

### Local Development

```
$ npm start
```

This command starts a local development server and opens up a browser window. Most changes are reflected live without having to restart the server.

To test a translation use `npm run start -- --locale es` for Spanish

### Build

```
$ npm run build
```

This command generates static content into the `build` directory and can be served using any static contents hosting service.

### Deployment

Use the provided Github Action, or deploy manually after building


## Docusaurus

This website is built using [Docusaurus](https://docusaurus.io/), a modern static website generator. Instructions are available on https://mongodb-developer.github.io/docusaurus-workshop/.

### Disclaimer

Use at your own risk; not a supported MongoDB product
