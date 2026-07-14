import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { describe, expect, it } from 'vitest';
import { AdminApiService } from './admin-api.service';
import { RoutesPageComponent } from './routes-page.component';

describe('RoutesPageComponent', () => {
  it('loads route rows from the native administrative endpoint', () => {
    const api = {
      get: () => of({ ok: true, message: null, data: { routes: [{ slug: 'admin-routes', route: '/admin/routes' }] }, errors: {} }),
      post: () => of({ ok: true, message: null, data: {}, errors: {} })
    };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(RoutesPageComponent);
    fixture.detectChanges();

    expect(fixture.componentInstance.routes()).toEqual([{ slug: 'admin-routes', route: '/admin/routes' }]);
    expect(fixture.nativeElement.textContent).toContain('/admin/routes');
  });

  it('keeps a structured backend error visible', () => {
    const api = {
      get: () => throwError(() => ({ ok: false, message: 'No disponible', data: null, errors: { routes: ['Inténtalo después'] } })),
      post: () => of({ ok: true, message: null, data: {}, errors: {} })
    };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(RoutesPageComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Inténtalo después');
  });
});
