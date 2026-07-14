import { HttpRequest, HttpResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { describe, expect, it } from 'vitest';
import { adminCsrfInterceptor } from './admin-csrf.interceptor';
import { AdminCsrfService } from './admin-csrf.service';

describe('adminCsrfInterceptor', () => {
  it('adds the session CSRF token only to mutations', () => {
    TestBed.configureTestingModule({ providers: [AdminCsrfService] });
    TestBed.inject(AdminCsrfService).set('csrf-token');
    let intercepted: HttpRequest<unknown> | undefined;
    const next = (request: HttpRequest<unknown>) => {
      intercepted = request;
      return of(new HttpResponse({ status: 200 }));
    };

    TestBed.runInInjectionContext(() => adminCsrfInterceptor(new HttpRequest('POST', '/admin/api/v2/users', {}), next)).subscribe();

    expect(intercepted?.headers.get('X-PSFS-CSRF')).toBe('csrf-token');
  });

  it('does not add a CSRF header to read-only requests', () => {
    TestBed.configureTestingModule({ providers: [AdminCsrfService] });
    let intercepted: HttpRequest<unknown> | undefined;
    const next = (request: HttpRequest<unknown>) => {
      intercepted = request;
      return of(new HttpResponse({ status: 200 }));
    };

    TestBed.runInInjectionContext(() => adminCsrfInterceptor(new HttpRequest('GET', '/admin/api/v2/users'), next)).subscribe();

    expect(intercepted?.headers.has('X-PSFS-CSRF')).toBe(false);
  });
});
