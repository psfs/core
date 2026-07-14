import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { AdminApiService } from './admin-api.service';
import { ConfigPageComponent } from './config-page.component';

const schema = {
  name: 'config',
  title: 'Configuración',
  fields: {
    'app.name': { name: 'app.name', label: 'Nombre', type: 'text', value: 'PSFS', required: true },
    'root.api.secret': { name: 'root.api.secret', label: 'Secreto', type: 'password', value: '', required: false }
  }
};

describe('ConfigPageComponent', () => {
  it('renders the safe schema without a configured secret value', () => {
    const api = { get: () => of({ ok: true, message: null, data: { form: schema }, errors: {} }), put: () => of({ ok: true, message: null, data: { changed: [] }, errors: {} }) };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(ConfigPageComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Nombre');
    expect(fixture.nativeElement.querySelector('input[type="password"]').value).toBe('');
  });

  it('maps structured 422 errors to their form control', () => {
    const api = {
      get: () => of({ ok: true, message: null, data: { form: schema }, errors: {} }),
      put: () => throwError(() => ({ ok: false, message: 'Configuración inválida', data: null, errors: { 'app.name': ['El nombre es obligatorio'] } }))
    };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(ConfigPageComponent);
    fixture.detectChanges();
    fixture.componentInstance.save({ values: { 'app.name': '' }, extra: {} });
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('El nombre es obligatorio');
    expect(fixture.componentInstance.fieldErrors()['app.name']).toEqual(['El nombre es obligatorio']);
  });

  it('offers a suggested key and submits it as an extra configuration entry', () => {
    const put = vi.fn(() => of({ ok: true, message: null, data: { changed: ['custom.flag'] }, errors: {} }));
    const api = {
      get: () => of({ ok: true, message: null, data: { form: schema, suggestions: ['custom.flag'] }, errors: {} }),
      put
    };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(ConfigPageComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Add parameter');
    fixture.componentInstance.addExtra();
    fixture.componentInstance.setExtra(0, 'custom.flag', 'enabled');
    fixture.componentInstance.save({ values: { 'app.name': 'PSFS' }, extra: fixture.componentInstance.extraValues() });

    expect(put).toHaveBeenCalledWith('config', { values: { 'app.name': 'PSFS' }, extra: { 'custom.flag': 'enabled' } });
  });
});
