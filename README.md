# WordPress Child Theme Build System

This build system automatically increments your theme version and creates a deployable zip file for your WordPress child theme.

## Folder Structure 

```
builds
  [builds go here]
inc/
  core/
    loader.php
    theme-setup.php
  modules/
    scroll-offset/
      admin.php
      frontend.php
      utilities.php
node_modules/
  [lots-of-modules]
.gitignore
build.js
functions.php
package-lock.json
package.json
README.md
style.css
```

## Setup

1. **Install Node.js** (if you haven't already): [Download from nodejs.org](https://nodejs.org/)

2. **Navigate to your theme directory** in VS Code terminal:
   ```bash
   cd path/to/your/theme
   ```

3. **Install dependencies**:
   ```bash
   npm install
   ```

## Usage

### Basic Commands

- **Patch version bump** (1.0.0 → 1.0.1):
  ```bash
  npm run build
  ```

- **Minor version bump** (1.0.0 → 1.1.0):
  ```bash
  npm run build:minor
  ```

- **Major version bump** (1.0.0 → 2.0.0):
  ```bash
  npm run build:major
  ```

### What happens during build:

1. ✅ Reads current version from `styles.css`
2. ✅ Increments version number
3. ✅ Updates version in `styles.css` header
4. ✅ Creates `builds/` directory (if needed)
5. ✅ Creates zip file: `hello-child-starter-vX.X.X.zip`

### Files included in zip:

- All PHP files (`functions.php`, `inc/` folder, etc.)
- `styles.css` (with updated version)
- Any other theme assets

### Files excluded from zip:

- `node_modules/`
- `builds/` directory
- `package.json`, `package-lock.json`
- `build.js`
- `.git/`, `.gitignore`
- `.vscode/`
- `README.md`

## Deployment

1. Run your build command
2. Navigate to the `builds/` folder
3. Upload the generated zip file to WordPress:
   - **Admin Dashboard** → **Appearance** → **Themes** → **Add New** → **Upload Theme**
   - Or via FTP to `/wp-content/themes/`

## Troubleshooting

**"Version not found" error:**
- Make sure your `styles.css` has a proper theme header with `Version: X.X.X`

**"archiver not found" error:**
- Run `npm install` to install dependencies

**Permission errors:**
- Make sure you have write permissions in your theme directory

## VS Code Integration

You can also run builds directly from VS Code:
1. Open the integrated terminal (`Ctrl + `` ` ``)
2. Run any of the npm commands above

For even faster access, add these to your VS Code tasks.json:
```json
{
    "version": "2.0.0",
    "tasks": [
        {
            "label": "Build Theme (Patch)",
            "type": "shell",
            "command": "npm run build",
            "group": "build"
        }
    ]
}
```