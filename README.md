# Drupal 10 Project with GraphQL

A modern Drupal 10 project with GraphQL integration, enhanced admin experience, and comprehensive content management capabilities.

## Features

- **Drupal 10.5** - Latest Drupal core
- **GraphQL Integration** - Complete GraphQL API with Compose modules
- **Modern Admin UI** - Gin theme with enhanced toolbar
- **Webform Support** - Advanced form building capabilities
- **SEO Tools** - Metatag and Pathauto for SEO optimization
- **Content Management** - Enhanced editing with Visual Editor
- **OAuth2 Integration** - Simple OAuth for API authentication

## Prerequisites

- **PHP 8.3+** with required extensions
- **Composer 2.0+** for dependency management
- **Database** - MySQL 8.0+ or MariaDB 10.11+
- **DDEV** (recommended) or local web server

## Quick Start with DDEV (Recommended)

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd bounty-42-drupal
   ```

2. **Configure and start DDEV environment**
   ```bash
   ddev config
   ddev start
   ```

3. **Install PHP dependencies**
   ```bash
   ddev composer install
   ```

4. **Configure settings for configuration import**
   ```bash
   # Add this line to the bottom of web/sites/default/settings.php
   echo "\$settings['config_sync_directory'] = '../config/sync';" >> web/sites/default/settings.php
   ```

5. **Install Drupal**
   ```bash
   ddev drush site:install --existing-config
   ```

6. **Access the site**
   - Site: `https://bounty-42-drupal.ddev.site`
   - Admin: `https://bounty-42-drupal.ddev.site/user/login`

## Local Development Setup

### 1. Environment Setup

```bash
# Clone repository
git clone <repository-url>
cd bounty-42-drupal

# Install PHP dependencies
composer install
```

## Configuration Management

### Export Configuration

```bash
ddev drush config:export
# or
./vendor/bin/drush config:export
```

### Import Configuration

```bash
ddev drush config:import
# or
./vendor/bin/drush config:import
```

## Project Structure

```
bounty-42-drupal/
├── composer.json          # PHP dependencies and project config
├── config/sync/           # Drupal configuration files
├── web/                   # Web root
│   ├── core/             # Drupal core files
│   ├── modules/          # Contrib and custom modules
│   ├── themes/           # Contrib and custom themes
│   ├── sites/default/    # Site-specific configuration
│   └── index.php         # Entry point
├── vendor/               # Composer dependencies
└── private.key/public.key # OAuth2 keys
```

## Key Modules & Features

### Content Management
- **Webform** - Advanced form builder with GraphQL integration
- **Visual Editor** - Enhanced content editing experience
- **Media Library** - Comprehensive media management

### API & Integration
- **GraphQL Compose** - Complete GraphQL API
- **Simple OAuth** - OAuth2 authentication for APIs
- **JSON:API** - RESTful API support

### Admin Experience
- **Gin Theme** - Modern admin interface
- **Admin Toolbar** - Enhanced admin navigation
- **Coffee** - Quick admin search

### SEO & Performance
- **Metatag** - Meta tag management
- **Pathauto** - Automatic URL aliases
- **Redirect** - URL redirection management

## GraphQL API

Access the GraphQL endpoint at `/graphql` with features:
- Complete schema for all content types
- Webform integration
- Preview capabilities
- Fragment support


## Common Commands

### DDEV Commands
```bash
ddev start                 # Start environment
ddev stop                  # Stop environment
ddev restart               # Restart environment
ddev composer install     # Install PHP dependencies
ddev drush status         # Check Drupal status
ddev drush cr             # Clear cache
ddev logs                 # View logs
```

### Drush Commands
```bash
drush status              # Site status
drush cr                  # Clear cache
drush config:export       # Export configuration
drush config:import       # Import configuration
drush user:create admin   # Create admin user
drush user:password admin # Reset admin password
```

## Troubleshooting

### Common Issues

1. **File permissions**: Ensure `web/sites/default/files` is writable
2. **Memory limits**: Increase PHP memory limit for large imports
3. **Cache issues**: Clear Drupal cache with `drush cr`

### Useful Commands

```bash
# Fix file permissions
chmod -R 755 web/sites/default/files

# Clear all caches
ddev drush cr

# Check system requirements
ddev drush requirements
```

## Contributing

1. Follow Drupal coding standards
2. Test configuration export/import
3. Update documentation for new features

## Support

- [Drupal Documentation](https://www.drupal.org/docs)
- [GraphQL Compose Documentation](https://www.drupal.org/project/graphql_compose)
- [DDEV Documentation](https://ddev.readthedocs.io/)

## License

GPL-2.0-or-later
