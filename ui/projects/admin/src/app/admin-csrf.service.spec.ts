import { describe, expect, it } from 'vitest';
import { AdminCsrfService } from './admin-csrf.service';

describe('AdminCsrfService', () => {
  it('stores a non-empty token and replays it to pending mutations', () => {
    const service = new AdminCsrfService();
    let issued = '';
    service.waitForToken().subscribe((token) => issued = token);

    service.set('csrf-token');

    expect(service.token()).toBe('csrf-token');
    expect(issued).toBe('csrf-token');
  });

  it('clears its current token without emitting an invalid value', () => {
    const service = new AdminCsrfService();
    let emissions = 0;
    service.waitForToken().subscribe(() => emissions++);

    service.set(null);

    expect(service.token()).toBe('');
    expect(emissions).toBe(0);
  });
});
