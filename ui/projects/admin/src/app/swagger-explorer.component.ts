import { ChangeDetectionStrategy, Component, OnInit, inject, signal } from '@angular/core';
import { NgIf } from '@angular/common';
import { ActivatedRoute } from '@angular/router';

declare global {
  interface Window {
    SwaggerUIBundle?: {
      (options: Record<string, unknown>): unknown;
      presets: { apis: unknown };
      plugins: { DownloadUrl: unknown };
    };
    SwaggerUIStandalonePreset?: unknown;
  }
}

@Component({
  selector: 'psfs-swagger-explorer',
  imports: [NgIf],
  template: `
    <article class="native-page swagger-page" aria-live="polite">
      <header class="page-header">
        <div>
          <p class="page-eyebrow">Integración</p>
          <h1>Documentación API · {{ domain() }}</h1>
          <p class="page-description">Explorador interactivo compatible con Swagger 2.0 y OpenAPI 3.1.</p>
        </div>
      </header>
      <p *ngIf="loading()" class="notice notice--info">Cargando explorador API…</p>
      <p *ngIf="error()" class="form-errors">{{ error() }}</p>
      <div id="swagger-ui" [class.swagger-ui--ready]="!loading() && !error()"></div>
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class SwaggerExplorerComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  readonly domain = signal('');
  readonly loading = signal(true);
  readonly error = signal('');

  async ngOnInit(): Promise<void> {
    const domain = this.route.snapshot.paramMap.get('domain') ?? '';
    this.domain.set(domain);
    if (!domain) {
      this.error.set('No se ha indicado un dominio para la documentación.');
      this.loading.set(false);
      return;
    }

    try {
      await this.loadSwaggerAssets();
      const bundle = window.SwaggerUIBundle;
      if (!bundle || !window.SwaggerUIStandalonePreset) throw new Error('Swagger UI no está disponible.');

      const requestedDocument = this.route.snapshot.queryParamMap.get('document');
      const documentUrl = requestedDocument && /^\/[A-Za-z0-9_/-]+\/api\/doc$/.test(requestedDocument)
        ? requestedDocument
        : `/${encodeURIComponent(domain.toUpperCase())}/api/doc`;
      bundle({
        urls: [
          { url: `${documentUrl}?type=swagger`, name: 'Swagger 2.0 (legacy)' },
          { url: `${documentUrl}?type=openapi`, name: 'OpenAPI 3.1' }
        ],
        'urls.primaryName': 'OpenAPI 3.1',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [bundle.presets.apis, window.SwaggerUIStandalonePreset],
        plugins: [bundle.plugins.DownloadUrl],
        layout: 'StandaloneLayout'
      });
    } catch (failure: unknown) {
      this.error.set(failure instanceof Error ? failure.message : 'No se pudo cargar Swagger UI.');
    } finally {
      this.loading.set(false);
    }
  }

  private async loadSwaggerAssets(): Promise<void> {
    this.ensureStyle('https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css');
    await this.ensureScript('https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js');
    await this.ensureScript('https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js');
  }

  private ensureStyle(source: string): void {
    if (document.querySelector(`link[data-psfs-swagger="${source}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = source;
    link.dataset['psfsSwagger'] = source;
    document.head.appendChild(link);
  }

  private ensureScript(source: string): Promise<void> {
    if (document.querySelector(`script[data-psfs-swagger="${source}"]`)) return Promise.resolve();
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = source;
      script.async = true;
      script.dataset['psfsSwagger'] = source;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error(`No se pudo cargar ${source}.`));
      document.head.appendChild(script);
    });
  }
}
