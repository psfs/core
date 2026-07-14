import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { describe, expect, it } from 'vitest';
import { AdminApiService } from './admin-api.service';
import { ModulesPageComponent } from './modules-page.component';

const form = {
  name: 'admin_modules', title: 'Módulos', fields: {
    module: { name: 'module', label: 'Nombre del módulo', type: 'text', value: '', required: true },
    controllerType: { name: 'controllerType', label: 'Tipo de controlador', type: 'select', value: 'Normal', required: false, options: { Normal: 'Normal', Auth: 'Autenticado' } },
    api: { name: 'api', label: 'Clase API personalizada', type: 'text', value: '', required: false }
  }
};

describe('ModulesPageComponent', () => {
  it('renders the native module form from the v2 schema', () => {
    const api = { get: () => of({ ok: true, message: null, data: { form }, errors: {} }), post: () => of({ ok: true, message: 'Módulo generado', data: { module: 'TEST' }, errors: {} }) };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(ModulesPageComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Generador de módulos');
    expect(fixture.nativeElement.querySelector('select#controllerType')).not.toBeNull();
    expect(fixture.nativeElement.querySelector('iframe')).toBeNull();
  });

  it('shows structured errors returned by an invalid generation request', () => {
    const api = {
      get: () => of({ ok: true, message: null, data: { form }, errors: {} }),
      post: () => throwError(() => ({ ok: false, message: 'Módulo inválido', data: null, errors: { module: ['El módulo es obligatorio'] } }))
    };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(ModulesPageComponent);
    fixture.detectChanges();
    fixture.componentInstance.create({ values: { module: '', controllerType: 'Normal', api: '' }, extra: {} });
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('El módulo es obligatorio');
  });
});
