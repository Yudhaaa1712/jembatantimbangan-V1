/**
 * Preload Script — IPC Bridge
 * Exposes safe APIs to renderer process via contextBridge
 */
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  getAppVersion: () => ipcRenderer.invoke('get-app-version'),
  getAppPath: () => ipcRenderer.invoke('get-app-path'),
  openExternal: (url) => ipcRenderer.invoke('open-external', url),
  printContent: () => ipcRenderer.invoke('print-content'),
  printStruk: () => ipcRenderer.invoke('print-struk'),
  showPrintDialog: () => ipcRenderer.invoke('show-print-dialog'),
  onPortSelector: (callback) => ipcRenderer.on('show-port-selector', (event, portList) => callback(portList)),
  selectPort: (portId) => ipcRenderer.send('port-selected', portId),
});
