import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { TestBed } from '@angular/core/testing';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SwaggerExplorerComponent } from './swagger-explorer.component';

describe('SwaggerExplorerComponent', () => {
  afterEach(() => {
    delete window.SwaggerUIBundle;
    delete window.SwaggerUIStandalonePreset;
  });

  function create(domain = 'client', document: string | null = null): SwaggerExplorerComponent {
    TestBed.configureTestingModule({
      providers: [{
        provide: ActivatedRoute,
        useValue: {
          snapshot: {
            paramMap: convertToParamMap(domain ? { domain } : {}),
            queryParamMap: convertToParamMap(document ? { document } : {})
          }
        }
      }]
    });
    return TestBed.createComponent(SwaggerExplorerComponent).componentInstance;
  }

  it('builds Swagger and OpenAPI URLs from the current domain', async () => {
    const component = create();
    const bundle = vi.fn();
    window.SwaggerUIBundle = Object.assign(bundle, { presets: { apis: {} }, plugins: { DownloadUrl: {} } });
    window.SwaggerUIStandalonePreset = {};
    vi.spyOn(component as any, 'loadSwaggerAssets').mockResolvedValue(undefined);

    await component.ngOnInit();

    expect(component.loading()).toBe(false);
    expect(component.error()).toBe('');
    expect(bundle).toHaveBeenCalledWith(expect.objectContaining({
      urls: [
        { url: '/CLIENT/api/doc?type=swagger', name: 'Swagger 2.0 (legacy)' },
        { url: '/CLIENT/api/doc?type=openapi', name: 'OpenAPI 3.1' }
      ]
    }));
  });

  it('uses a validated document override and rejects unavailable Swagger assets', async () => {
    const component = create('client', '/client/api/doc');
    vi.spyOn(component as any, 'loadSwaggerAssets').mockRejectedValue(new Error('Offline'));

    await component.ngOnInit();

    expect(component.loading()).toBe(false);
    expect(component.error()).toBe('Offline');
  });

  it('reports a missing domain without loading assets', async () => {
    const component = create('');
    const loader = vi.spyOn(component as any, 'loadSwaggerAssets');

    await component.ngOnInit();

    expect(component.error()).toContain('dominio');
    expect(component.loading()).toBe(false);
    expect(loader).not.toHaveBeenCalled();
  });
});
