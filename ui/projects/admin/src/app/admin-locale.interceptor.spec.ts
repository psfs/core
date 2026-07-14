import { HttpRequest, HttpResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { afterEach, describe, expect, it } from 'vitest';
import { adminLocaleInterceptor } from './admin-locale.interceptor';
import { AdminLocaleService } from './admin-locale.service';

describe('adminLocaleInterceptor', () => {
  afterEach(() => localStorage.clear());

  it('sends the stored locale to Admin v2 APIs only', () => {
    TestBed.configureTestingModule({ providers: [AdminLocaleService] });
    TestBed.inject(AdminLocaleService).set('es_ES');
    let intercepted: HttpRequest<unknown> | undefined;
    const next = (request: HttpRequest<unknown>) => {
      intercepted = request;
      return of(new HttpResponse({ status: 200 }));
    };

    TestBed.runInInjectionContext(() => adminLocaleInterceptor(new HttpRequest('GET', '/admin/api/v2/config'), next)).subscribe();

    expect(intercepted?.headers.get('X-API-LANG')).toBe('es_ES');
  });

  it('does not modify requests outside Admin v2', () => {
    TestBed.configureTestingModule({ providers: [AdminLocaleService] });
    let intercepted: HttpRequest<unknown> | undefined;
    const next = (request: HttpRequest<unknown>) => {
      intercepted = request;
      return of(new HttpResponse({ status: 200 }));
    };

    TestBed.runInInjectionContext(() => adminLocaleInterceptor(new HttpRequest('GET', '/api/health'), next)).subscribe();

    expect(intercepted?.headers.has('X-API-LANG')).toBe(false);
  });
});
