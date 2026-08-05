// electron-app/server/helpers/auto-serial-connect.test.js
const fs = require('fs');
const path = require('path');
const vm = require('vm');

describe('AutoSerialConnector - scale indicator parser unit tests', () => {
  let AutoSerialConnector;

  beforeAll(() => {
    // Dari server/helpers/ → renderer/js/. Path lama ('../../../js/...') keluar
    // dari folder proyek sehingga seluruh suite ini gagal dengan ENOENT.
    const filePath = path.join(__dirname, '../../renderer/js/auto-serial-connect.js');
    const content = fs.readFileSync(filePath, 'utf8');
    
    // Create mock browser context
    const context = {
      console,
      navigator: {
        serial: {
          getPorts: jest.fn().mockResolvedValue([]),
          requestPort: jest.fn(),
          addEventListener: jest.fn()
        }
      },
      localStorage: {
        getItem: jest.fn().mockReturnValue(null),
        setItem: jest.fn(),
        removeItem: jest.fn()
      },
      Event: class {},
      document: {
        getElementById: jest.fn(),
        addEventListener: jest.fn(),
        readyState: 'complete',
        visibilityState: 'visible'
      },
      addEventListener: jest.fn(),
      location: {
        href: 'http://localhost'
      },
      setTimeout,
      clearTimeout,
      setInterval,
      clearInterval,
      TextDecoder,
      TextEncoder
    };
    context.window = context;
    
    vm.createContext(context);
    vm.runInContext(content, context);
    AutoSerialConnector = context.AutoSerialConnector;
  });

  test('1. Sonic / Generic Indicator parser (Default patterns)', () => {
    const connector = new AutoSerialConnector({
      indicatorModel: 'generic'
    });

    expect(connector.parseSonicA283Data('wn00012500kg')).toBe(12500);
    expect(connector.parseSonicA283Data('12345 kg')).toBe(12345);
    expect(connector.parseSonicA283Data('ST,GS, +000123.5 KG')).toBe(123.5);
    expect(connector.parseSonicA283Data('   00123.5')).toBe(123.5);
  });

  test('2. GSC GST-9600 Indicator parser', () => {
    const connector = new AutoSerialConnector({
      indicatorModel: 'gsc'
    });

    expect(connector.parseSonicA283Data('ST,GS,+0012350kg\r\n')).toBe(12350);
    expect(connector.parseSonicA283Data('US,GS,-0002500kg\r\n')).toBe(-2500);
    expect(connector.parseSonicA283Data('GS,+0009999\r\n')).toBe(9999);
  });

  test('3. CAS CI-2001 Indicator parser', () => {
    const connector = new AutoSerialConnector({
      indicatorModel: 'cas'
    });

    expect(connector.parseSonicA283Data('ST,NT,+  1250.0  kg\r\n')).toBe(1250);
    expect(connector.parseSonicA283Data('US,GS,-   500.5  kg\r\n')).toBe(-500.5);
  });

  test('4. Boston / Genwin continuous mode parser', () => {
    const connector = new AutoSerialConnector({
      indicatorModel: 'boston'
    });

    expect(connector.parseSonicA283Data('+00012345')).toBe(12345);
    expect(connector.parseSonicA283Data('-00000450')).toBe(-450);
  });

  test('5. Custom Regex Parser provided in settings', () => {
    const connector = new AutoSerialConnector({
      indicatorModel: 'custom',
      customRegex: 'WT:([+-]?\\d+)'
    });

    expect(connector.parseSonicA283Data('HEADER WT:+0012500 STATUS:ST')).toBe(12500);
    expect(connector.parseSonicA283Data('WT:-0002500')).toBe(-2500);
    expect(connector.parseSonicA283Data('NO MATCH')).toBeNull();
  });

  test('6. Robustness: Parser resilience with corrupted/fragmented data', () => {
    const connector = new AutoSerialConnector({
      indicatorModel: 'generic'
    });

    // Test corrupted prefix
    expect(connector.parseSonicA283Data('???wn00012500kg')).toBe(12500);

    // Test multiple garbage prefixes
    expect(connector.parseSonicA283Data('garbage-123-ST,GS, +000123.5 KG')).toBe(123.5);

    // Test missing units or trailing characters
    expect(connector.parseSonicA283Data('wn00012500')).toBe(12500);

    // Test invalid characters that shouldn't parse
    expect(connector.parseSonicA283Data('abcdefgh')).toBeNull();
    expect(connector.parseSonicA283Data('')).toBeNull();
    expect(connector.parseSonicA283Data('wn      kg')).toBeNull();
  });

  test('7. Robustness: Stream fragmentation handling in handleIncomingData', () => {
    const onDataMock = jest.fn();
    const connector = new AutoSerialConnector({
      indicatorModel: 'generic',
      onData: onDataMock
    });

    // Simulate first half of a frame
    const part1 = new TextEncoder().encode('wn00012');
    connector.handleIncomingData(part1);
    expect(onDataMock).not.toHaveBeenCalled();

    // Simulate second half of the frame with a newline
    const part2 = new TextEncoder().encode('500kg\r\n');
    connector.handleIncomingData(part2);
    
    expect(onDataMock).toHaveBeenCalledTimes(1);
    expect(onDataMock).toHaveBeenCalledWith(12500);
  });

  test('8. Robustness: Buffer overflow prevention', () => {
    const connector = new AutoSerialConnector({
      indicatorModel: 'generic'
    });

    // Make the buffer very large
    connector.readBuffer = 'a'.repeat(2000);
    
    // Send some new data
    const data = new TextEncoder().encode('wn00010000kg\r\n');
    connector.handleIncomingData(data);

    // Verify buffer was trimmed to prevent memory leakage
    expect(connector.readBuffer.length).toBeLessThanOrEqual(1024);
  });

  test('9. Robustness: Non-fatal and fatal read errors handling in readLoop', async () => {
    const onDisconnectMock = jest.fn();
    const connector = new AutoSerialConnector({
      indicatorModel: 'generic',
      autoReconnect: false, // Disable auto reconnect to prevent background timer leaks in test
      onDisconnect: onDisconnectMock
    });

    // Mock port and reader
    const mockReader = {
      read: jest.fn()
        .mockRejectedValueOnce(new Error('Framing error (non-fatal)')) // First call throws non-fatal
        .mockRejectedValueOnce(new Error('Device has been lost (fatal)')), // Second call throws fatal
      releaseLock: jest.fn()
    };
    
    const mockPort = {
      readable: {
        getReader: () => mockReader
      }
    };

    connector.port = mockPort;
    connector.isConnected = true;

    // We spy on handleDisconnection
    const handleDisconnectionSpy = jest.spyOn(connector, 'handleDisconnection').mockImplementation(() => {});

    // Start reading
    connector.startReading();

    // Wait 1200ms (more than the 1000ms non-fatal retry interval) to allow the retry to trigger
    await new Promise(resolve => setTimeout(resolve, 1200));

    // The read loop should have caught the non-fatal error, and called startReading again
    // Then on the second attempt, it catches the fatal error and calls handleDisconnection
    expect(handleDisconnectionSpy).toHaveBeenCalledTimes(1);
    
    // Cleanup spy
    handleDisconnectionSpy.mockRestore();
  });
});

