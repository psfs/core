import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { TestBed } from '@angular/core/testing';
import { Subject } from 'rxjs';
import { describe, expect, it } from 'vitest';
import { AdminNativePageComponent } from './admin-shell.component';

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
