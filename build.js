const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

// Configuration
const THEME_CSS_FILE = 'style.css';
const BUILD_DIR = 'builds';
const THEME_NAME = 'hello-child-starter';

// Files to exclude from the zip
const EXCLUDE_FILES = [
    'node_modules',
    'builds',
    'package.json',
    'package-lock.json',
    'build.js',
    '.git',
    '.gitignore',
    '.vscode',
    'README.md',
    'BUILD_README.md'
];

/**
 * Parse version from style.css
 */
function getCurrentVersion() {
    try {
        const cssContent = fs.readFileSync(THEME_CSS_FILE, 'utf8');
        const versionMatch = cssContent.match(/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/);
        
        if (!versionMatch) {
            throw new Error('Version not found in style.css');
        }
        
        return versionMatch[1];
    } catch (error) {
        console.error('Error reading current version:', error.message);
        process.exit(1);
    }
}

/**
 * Increment version number
 */
function incrementVersion(version, type = 'patch') {
    const parts = version.split('.').map(Number);
    
    switch (type) {
        case 'major':
            parts[0]++;
            parts[1] = 0;
            parts[2] = 0;
            break;
        case 'minor':
            parts[1]++;
            parts[2] = 0;
            break;
        case 'patch':
        default:
            parts[2]++;
            break;
    }
    
    return parts.join('.');
}

/**
 * Update version in style.css
 */
function updateVersionInCSS(newVersion) {
    try {
        let cssContent = fs.readFileSync(THEME_CSS_FILE, 'utf8');
        cssContent = cssContent.replace(
            /Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/,
            `Version: ${newVersion}`
        );
        fs.writeFileSync(THEME_CSS_FILE, cssContent);
        console.log(`✅ Updated ${THEME_CSS_FILE} to version ${newVersion}`);
    } catch (error) {
        console.error('Error updating CSS file:', error.message);
        process.exit(1);
    }
}

/**
 * Create builds directory if it doesn't exist
 */
function ensureBuildDir() {
    if (!fs.existsSync(BUILD_DIR)) {
        fs.mkdirSync(BUILD_DIR);
        console.log(`📁 Created ${BUILD_DIR} directory`);
    }
}

/**
 * Check if file/directory should be excluded
 */
function shouldExclude(fileName) {
    return EXCLUDE_FILES.some(exclude => {
        return fileName === exclude || fileName.startsWith(exclude + '/');
    });
}

/**
 * Create zip file
 */
function createZip(version) {
    return new Promise((resolve, reject) => {
        const zipFileName = `${THEME_NAME}-v${version}.zip`;
        const zipPath = path.join(BUILD_DIR, zipFileName);
        
        const output = fs.createWriteStream(zipPath);
        const archive = archiver('zip', {
            zlib: { level: 9 } // Maximum compression
        });

        output.on('close', () => {
            const fileSize = (archive.pointer() / 1024 / 1024).toFixed(2);
            console.log(`📦 Created ${zipFileName} (${fileSize} MB)`);
            resolve(zipPath);
        });

        archive.on('error', (err) => {
            reject(err);
        });

        archive.pipe(output);

        // Add all files except excluded ones
        const addDirectory = (dirPath, basePath = '') => {
            const items = fs.readdirSync(dirPath);
            
            items.forEach(item => {
                const fullPath = path.join(dirPath, item);
                const relativePath = path.join(basePath, item);
                
                if (shouldExclude(relativePath)) {
                    return;
                }
                
                const stats = fs.statSync(fullPath);
                
                if (stats.isDirectory()) {
                    addDirectory(fullPath, relativePath);
                } else {
                    // Prefix all file paths with THEME_NAME to ensure consistent folder name when unzipped
                    const archivePath = path.join(THEME_NAME, relativePath);
                    archive.file(fullPath, { name: archivePath });
                }
            });
        };

        addDirectory('.');
        archive.finalize();
    });
}

/**
 * Main build function
 */
async function build() {
    const versionType = process.argv[2] || 'patch';
    
    if (!['patch', 'minor', 'major'].includes(versionType)) {
        console.error('❌ Invalid version type. Use: patch, minor, or major');
        process.exit(1);
    }
    
    console.log(`🚀 Starting Hello Child Theme build process (${versionType} version bump)...`);
    
    try {
        // Get current version
        const currentVersion = getCurrentVersion();
        console.log(`📋 Current version: ${currentVersion}`);
        
        // Increment version
        const newVersion = incrementVersion(currentVersion, versionType);
        console.log(`📋 New version: ${newVersion}`);
        
        // Update CSS file
        updateVersionInCSS(newVersion);
        
        // Ensure build directory exists
        ensureBuildDir();
        
        // Create zip file
        await createZip(newVersion);
        
        console.log(`✅ Build completed successfully!`);
        console.log(`📁 Theme package: builds/${THEME_NAME}-v${newVersion}.zip`);
        
    } catch (error) {
        console.error('❌ Build failed:', error.message);
        process.exit(1);
    }
}

// Run the build
build();
