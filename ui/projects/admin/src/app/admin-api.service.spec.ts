import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { type AdminEnvelope } from './admin-contracts';
import { AdminApiService } from './admin-api.service';

describe('AdminApiService', () => {
  let service: AdminApiService;
  let request: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()]
    });

    service = TestBed.inject(AdminApiService);
    request = TestBed.inject(HttpTestingController);
  });

  afterEach(() => request.verify());

  it('unwraps a successful administrative envelope', () => {
    service.get<{ value: string }>('/admin/api/v2/example').subscribe((result) => {
      expect(result.data.value).toBe('ok');
      const errors: Record<string, string[]> = result.errors;
      expect(Array.isArray(errors)).toBe(false);
      expect(errors).toEqual({});
    });

    request.expectOne('/admin/api/v2/example').flush({
      ok: true,
      message: null,
      data: { value: 'ok' },
      errors: {}
    });
  });

  it('models empty errors as a map in the administrative contract', () => {
    const envelope: AdminEnvelope<{ value: string }> = {
      ok: true,
      message: null,
      data: { value: 'ok' },
      errors: {}
    };

    expect(Array.isArray(envelope.errors)).toBe(false);
  });

  it('keeps structured backend errors', () => {
    service.put('/admin/api/v2/example', {}).subscribe({
      error: (error) => expect(error.errors.name).toEqual(['Required'])
    });

    request.expectOne('/admin/api/v2/example').flush({
      ok: false,
      message: 'Invalid form',
      data: null,
      errors: { name: ['Required'] }
    }, { status: 422, statusText: 'Unprocessable Entity' });
  });
});
