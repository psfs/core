import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Subject } from 'rxjs';
import { afterEach, describe, expect, it } from 'vitest';
import { AdminNativePageComponent, AdminShellComponent } from './admin-shell.component';

describe('AdminShellComponent', () => {
  let request: HttpTestingController;

  function create(): ReturnType<typeof TestBed.createComponent<AdminShellComponent>> {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()]
    });
    request = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(AdminShellComponent);
    fixture.detectChanges();
    return fixture;
  }

  function bootstrap(): void {
    request.expectOne('/admin/api/v2/bootstrap').flush({
      identity: { username: 'admin', role: 'Administrator' },
      locale: 'en_US',
      locales: ['en_US', 'es_ES'],
      csrfToken: 'csrf-token',
      menu: []
    });
  }

  afterEach(() => request?.verify());

  it('waits for bootstrap before creating the child router outlet', () => {
    const fixture = create();

    expect(fixture.nativeElement.querySelector('router-outlet')).toBeNull();
    bootstrap();
    fixture.detectChanges();

    expect(fixture.componentInstance.bootstrap()?.identity.username).toBe('admin');
    expect(fixture.nativeElement.querySelector('router-outlet')).not.toBeNull();
  });

  it('persists a valid locale and refreshes bootstrap after the backend accepts it', () => {
    const fixture = create();
    bootstrap();
    fixture.componentInstance.switchLocale('es_ES');

    request.expectOne('/admin/api/v2/locale/es_ES').flush({ ok: true, data: { locale: 'es_ES' } });
    request.expectOne('/admin/api/v2/bootstrap').flush({
      identity: { username: 'admin', role: 'Administrator' },
      locale: 'es_ES', locales: ['en_US', 'es_ES'], csrfToken: 'csrf-token', menu: []
    });

    expect(fixture.componentInstance.bootstrap()?.locale).toBe('es_ES');
  });

  it('does not call the backend for an invalid locale and handles navigation state', () => {
    const fixture = create();
    bootstrap();

    fixture.componentInstance.switchLocale('../invalid');
    fixture.componentInstance.toggleNavigation();
    expect(fixture.componentInstance.navigationOpen()).toBe(true);
    fixture.componentInstance.closeNavigation();

    expect(fixture.componentInstance.navigationOpen()).toBe(false);
    expect(fixture.componentInstance.navigationSymbol('fa-cogs')).toBe('⚙');
    expect(fixture.componentInstance.navigationSymbol('unknown')).toBe('•');
  });
});

describe('AdminNativePageComponent', () => {
  it('keeps loading until its declared native page operation completes', () => {
    const load = new Subject<void>();
    TestBed.configureTestingModule({
      providers: [{
        provide: ActivatedRoute,
        useValue: {
          snapshot: {
            data: { page: { title: 'Operación nativa', description: 'Descripción', load: () => load } },
            paramMap: convertToParamMap({})
          }
        }
      }]
    });

    const fixture = TestBed.createComponent(AdminNativePageComponent);
    fixture.detectChanges();
    expect(fixture.componentInstance.state()).toBe('loading');

    load.next();
    expect(fixture.componentInstance.state()).toBe('success');
  });

  it('shows a structured error when a route has no native page contract', () => {
    TestBed.configureTestingModule({
      providers: [{
        provide: ActivatedRoute,
        useValue: { snapshot: { data: {}, paramMap: convertToParamMap({ domain: 'example', api: 'manager' }) } }
      }]
    });

    const fixture = TestBed.createComponent(AdminNativePageComponent);
    fixture.detectChanges();

    expect(fixture.componentInstance.state()).toBe('error');
    expect(fixture.componentInstance.error()?.message).toContain('contrato');
  });
});
