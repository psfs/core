import { describe, expect, it } from 'vitest';
import { AdminTranslationService } from './admin-translation.service';

describe('AdminTranslationService', () => {
  it('translates both legacy Spanish and API English labels reactively', () => {
    const service = new AdminTranslationService();
    service.setLocale('es_ES');
    expect(service.translate('General configuration')).toBe('Configuración general');
    expect(service.translate('API documentation')).toBe('Documentación API');

    service.setLocale('en_US');
    expect(service.translate('Configuración general')).toBe('General configuration');
    expect(service.translate('Documentación API')).toBe('API documentation');
  });

  it('translates page copy and interpolated values in both locales', () => {
    const service = new AdminTranslationService();
    service.setLocale('en_US');
    expect(service.translate('Elige un explorador para {{domain}}. No se incrusta una pantalla legacy.', { domain: 'PAYMENTS' }))
      .toBe('Choose an explorer for PAYMENTS. A legacy screen is not embedded.');
    expect(service.translate('Se eliminará el usuario {{username}}. Esta acción no se puede deshacer.', { username: 'admin' }))
      .toBe('User admin will be deleted. This action cannot be undone.');
    expect(service.translate('Este manager se muestra en modo consulta. Las altas, cambios y borrados se habilitarán únicamente mediante un endpoint v2 que aplique CSRF y autorización en el servidor.'))
      .toContain('read-only mode');

    service.setLocale('es_ES');
    expect(service.translate('Choose an explorer for {{domain}}. A legacy screen is not embedded.', { domain: 'PAYMENTS' }))
      .toBe('Elige un explorador para PAYMENTS. No se incrusta una pantalla legacy.');
    expect(service.translate('Page')).toBe('Página');
  });
});
