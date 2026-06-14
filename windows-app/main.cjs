const { app, BrowserWindow, Menu, screen, shell } = require('electron');
const fs = require('fs');
const path = require('path');

const configPath = path.join(__dirname, 'app.config.json');
const requiredAppUrl = 'https://hotel.twinsofte.com/public';
const defaultConfig = {
    appName: 'Twinsofte Hotel POS',
    appUrl: requiredAppUrl,
};

function readConfig() {
    try {
        return {
            ...defaultConfig,
            ...JSON.parse(fs.readFileSync(configPath, 'utf8')),
        };
    } catch {
        return defaultConfig;
    }
}

function normalizeAppUrl(appUrl) {
    try {
        const url = new URL(appUrl || requiredAppUrl);

        url.protocol = 'https:';
        url.hostname = 'hotel.twinsofte.com';
        url.pathname = '/public';
        url.search = '';
        url.hash = '';

        return url;
    } catch {
        return new URL(requiredAppUrl);
    }
}

function resolveUrl(targetUrl, baseUrl) {
    try {
        return new URL(targetUrl, baseUrl);
    } catch {
        return null;
    }
}

function isAllowedAppUrl(targetUrl, appUrl) {
    const resolvedUrl = resolveUrl(targetUrl, appUrl);

    if (!resolvedUrl) {
        return false;
    }

    const basePath = appUrl.pathname.replace(/\/$/, '');

    return ['http:', 'https:'].includes(resolvedUrl.protocol)
        && resolvedUrl.origin === appUrl.origin
        && (resolvedUrl.pathname === basePath || resolvedUrl.pathname.startsWith(`${basePath}/`));
}

function sendHome(window, appUrl) {
    if (window.webContents.getURL() !== appUrl.href) {
        window.loadURL(appUrl.href);
    }
}

function windowBounds() {
    const display = screen.getPrimaryDisplay();

    return display.workArea;
}

function createWindow() {
    const config = readConfig();
    const appUrl = normalizeAppUrl(config.appUrl);
    const bounds = windowBounds();

    const window = new BrowserWindow({
        x: bounds.x,
        y: bounds.y,
        width: bounds.width,
        height: bounds.height,
        minWidth: 1024,
        minHeight: 700,
        title: config.appName,
        show: false,
        autoHideMenuBar: true,
        backgroundColor: '#f8fafc',
        webPreferences: {
            preload: path.join(__dirname, 'preload.cjs'),
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: true,
        },
    });

    Menu.setApplicationMenu(null);

    window.once('ready-to-show', () => {
        window.setBounds(windowBounds());
        window.maximize();
        window.show();
        window.focus();
    });

    window.webContents.setWindowOpenHandler(({ url }) => {
        if (isAllowedAppUrl(url, appUrl)) {
            window.loadURL(url);

            return { action: 'deny' };
        }

        if (resolveUrl(url, appUrl)?.hostname === 'twinsofte.com') {
            sendHome(window, appUrl);

            return { action: 'deny' };
        }

        if (resolveUrl(url, appUrl)) {
            shell.openExternal(url);

            return { action: 'deny' };
        }

        return { action: 'allow' };
    });

    window.webContents.on('will-navigate', (event, url) => {
        if (!isAllowedAppUrl(url, appUrl)) {
            event.preventDefault();
            sendHome(window, appUrl);
        }
    });

    window.webContents.on('did-navigate', (_event, url) => {
        if (!isAllowedAppUrl(url, appUrl)) {
            sendHome(window, appUrl);
        }
    });

    window.webContents.on('did-redirect-navigation', (_event, url) => {
        if (!isAllowedAppUrl(url, appUrl)) {
            sendHome(window, appUrl);
        }
    });

    window.loadURL(appUrl.href);
}

const gotSingleInstanceLock = app.requestSingleInstanceLock();

if (!gotSingleInstanceLock) {
    app.quit();
}

app.whenReady().then(() => {
    const config = readConfig();

    app.setName(config.appName || defaultConfig.appName);
    createWindow();
});

app.on('second-instance', () => {
    const existingWindow = BrowserWindow.getAllWindows()[0];

    if (existingWindow) {
        if (existingWindow.isMinimized()) {
            existingWindow.restore();
        }

        existingWindow.focus();
    }
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
    }
});
