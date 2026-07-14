import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { describe, expect, it } from 'vitest';
import { AdminApiService } from './admin-api.service';
import { DocumentationPageComponent } from './documentation-page.component';

describe('DocumentationPageComponent', () => {
  it('offers an interactive API explorer instead of exposing raw OpenAPI JSON as the primary view', () => {
    const api = {
      get: (path: string) => path === 'docs'
        ? of({ ok: true, message: null, data: { domains: ['Sales'] }, errors: {} })
        : of({ ok: true, message: null, data: { openapi: '3.1.0', info: { title: 'Sales' } }, errors: {} })
    };
    TestBed.configureTestingModule({ providers: [provideRouter([]), { provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(DocumentationPageComponent);
    fixture.detectChanges();
    fixture.componentInstance.selectDomain('Sales');
    fixture.detectChanges();

    expect(fixture.componentInstance.domains()).toEqual(['Sales']);
    expect(fixture.nativeElement.textContent).toContain('Abrir explorador API');
    expect(fixture.nativeElement.querySelector('pre')).toBeNull();
  });

  it('keeps a structured backend error visible', () => {
    const api = { get: () => throwError(() => ({ ok: false, message: 'No disponible', data: null, errors: { docs: ['Sin acceso'] } })) };
    TestBed.configureTestingModule({ providers: [provideRouter([]), { provide: AdminApiService, useValue: api }] });

    const fixture = TestBed.createComponent(DocumentationPageComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Sin acceso');
  });
});
