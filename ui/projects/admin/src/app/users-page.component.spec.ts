import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { describe, expect, it } from 'vitest';
import { AdminApiService } from './admin-api.service';
import { UsersPageComponent } from './users-page.component';

const form = {
  name: 'admin_setup', title: 'Usuarios', fields: {
    username: { name: 'username', label: 'Alias', type: 'text', value: '', required: true },
    password: { name: 'password', label: 'Contraseña', type: 'password', value: '', required: true },
    profile: { name: 'profile', label: 'Rol', type: 'select', value: 'manager', required: true, options: { manager: 'Manager' } }
  }
};

describe('UsersPageComponent', () => {
  it('renders native users returned by the v2 contract', () => {
    const api = { get: () => of({ ok: true, message: null, data: { users: [{ username: 'alice', role: 'Manager', class: 'warning' }], form, profiles: { manager: 'Manager' } }, errors: {} }), post: () => of({ ok: true, message: 'Usuario creado', data: {}, errors: {} }) };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(UsersPageComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('alice');
    expect(fixture.nativeElement.textContent).toContain('Manager');
    expect(fixture.nativeElement.querySelector('iframe')).toBeNull();
  });

  it('shows structured validation errors returned by an invalid creation request', () => {
    const api = {
      get: () => of({ ok: true, message: null, data: { users: [], form, profiles: { manager: 'Manager' } }, errors: {} }),
      post: () => throwError(() => ({ ok: false, message: 'Usuario inválido', data: null, errors: { username: ['El alias es obligatorio'] } }))
    };
    TestBed.configureTestingModule({ providers: [{ provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(UsersPageComponent);
    fixture.detectChanges();
    fixture.componentInstance.create({ values: { username: '', password: '', profile: 'manager' }, extra: {} });
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('El alias es obligatorio');
    expect(fixture.componentInstance.fieldErrors()['username']).toEqual(['El alias es obligatorio']);
  });
});
