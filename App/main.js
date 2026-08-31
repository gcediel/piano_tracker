const { app, BrowserWindow, shell } = require('electron');
const path = require('path');
const config = require('./server/config');

let mainWindow = null;

function createWindow() {
  require('./server/index');

  mainWindow = new BrowserWindow({
    width: 1280,
    height: 900,
    minWidth: 900,
    minHeight: 600,
    icon: path.join(__dirname, 'assets', 'img', 'icon.png'),
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
    },
  });

  mainWindow.setMenuBarVisibility(false);

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith('http://') || url.startsWith('https://')) {
      shell.openExternal(url);
      return { action: 'deny' };
    }
    return { action: 'allow' };
  });

  // Zoom ajustable con teclado: Ctrl/Cmd +, Ctrl/Cmd -, Ctrl/Cmd 0
  mainWindow.webContents.on('before-input-event', (event, input) => {
    if (input.type !== 'keyDown' || !(input.control || input.meta)) return;
    const wc = mainWindow.webContents;
    if (input.key === '+' || input.key === '=') {
      wc.setZoomFactor(Math.min(2.0, wc.getZoomFactor() + 0.1));
      event.preventDefault();
    } else if (input.key === '-') {
      wc.setZoomFactor(Math.max(0.6, wc.getZoomFactor() - 0.1));
      event.preventDefault();
    } else if (input.key === '0') {
      wc.setZoomFactor(1.0);
      event.preventDefault();
    }
  });

  setTimeout(() => {
    mainWindow.loadURL(`http://127.0.0.1:${config.port}`);
  }, 300);
}

app.whenReady().then(createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) createWindow();
});
