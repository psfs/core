import { afterEach, describe, expect, it } from 'vitest';
import { AdminLocaleService } from './admin-locale.service';

describe('AdminLocaleService', () => {
  afterEach(() => localStorage.clear());

  it('persists and restores the selected locale', () => {
    const service = new AdminLocaleService();

    service.set('es_ES');

    expect(service.current()).toBe('es_ES');
  });
});
