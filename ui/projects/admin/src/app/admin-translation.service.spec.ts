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
});
